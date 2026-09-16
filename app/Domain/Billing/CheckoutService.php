<?php

namespace App\Domain\Billing;

use App\Domain\Billing\Exceptions\InvalidCheckoutStateException;
use App\Domain\Billing\Exceptions\PendingOrdersExistException;
use App\Domain\Billing\Exceptions\TenantMismatchException;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ServiceRequest;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    /**
     * تجميد الحساب للزيارة وحساب المبلغ النهائي (Checkout)
     */
    public function checkout(TableSession $session, User $staff): TableSession
    {
        // 0. فحص تطابق الـ Tenant قبل الدخول للـ Transaction
        // (BUG FIX: كان ناقص هنا؛ نفس الفحص اللي كاين فـ CreateOrderAction)
        if (app()->bound('current_cafe_id') && (int) app('current_cafe_id') !== (int) $session->cafe_id) {
            throw new TenantMismatchException((int) app('current_cafe_id'), (int) $session->cafe_id);
        }

        return DB::transaction(function () use ($session, $staff) {
            // 1. قفل الزيارة للتأكد من الحالة وتفادي التزامن (Race Conditions)
            // (BUG FIX: زدنا where('cafe_id', ...) صراحة، ما نتوكلوش غير على الـ Global Scope)
            $lockedSession = TableSession::where('id', $session->id)
                ->where('cafe_id', $session->cafe_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedSession->isOpen()) {
                throw new InvalidCheckoutStateException($lockedSession->status);
            }

            // 2. التحقق: واش كاين شي طلبات مازال ما تسلماتش؟
            // (BUG FIX: زدنا where('cafe_id', ...) صراحة)
            $pendingOrdersCount = Order::where('cafe_id', $lockedSession->cafe_id)
                ->where('table_session_id', $lockedSession->id)
                ->whereIn('status', ['new', 'accepted', 'preparing', 'ready'])
                ->count();

            if ($pendingOrdersCount > 0) {
                throw new PendingOrdersExistException($pendingOrdersCount);
            }

            // 3. حل وإغلاق أي طلب خدمة مازال مفتوح
            // (BUG FIX: زدنا 'acknowledged' — طلب متابَع بعد ما كيتسدش، خاصو يتحسم هو زعما)
            // (BUG FIX: زدنا where('cafe_id', ...) صراحة)
            ServiceRequest::where('cafe_id', $lockedSession->cafe_id)
                ->where('table_session_id', $lockedSession->id)
                ->whereIn('status', ['open', 'acknowledged'])
                ->update([
                    'status' => 'resolved',
                    'handled_by' => $staff->id,
                ]);

            // 4. الحساب المالي (Total): جمع المبالغ من أسطر الطلب المرتبطة بطلبات served فقط
            // (BUG FIX: زدنا where('cafe_id', ...) صراحة فالـ whereHas)
            $totalFinal = OrderItem::where('cafe_id', $lockedSession->cafe_id)
                ->whereHas('order', function ($query) use ($lockedSession) {
                    $query->where('cafe_id', $lockedSession->cafe_id)
                          ->where('table_session_id', $lockedSession->id)
                          ->where('status', 'served');
                })->sum('line_total');

            // (BUG FIX الحاسم: كان فيه (float) قبل number_format، وهادشي كيقدر
            // يضيع دقة الأرقام الكبيرة (Float Precision Loss). الدستور صريح:
            // "DECIMAL مضبوط، ماشي float" (القسم 09). كنستعملو bcadd باش
            // نضمنو دقة السنتيم حتى فالمبالغ الكبيرة، بلا ما نمرو من float.)
            $totalFinal = bcadd((string) $totalFinal, '0.00', 2);

            // 5. تحديث وتجميد الجلسة
            $beforeState = $lockedSession->only(['status', 'checkout_at', 'checkout_by', 'total_final']);

            $lockedSession->update([
                'status' => 'checkout',
                'checkout_at' => now(),
                'checkout_by' => $staff->id,
                'total_final' => $totalFinal,
            ]);

            // 6. توثيق العملية فـ audit_logs
            // (BUG FIX: زدنا 'cafe_id' صراحة بدل ما نتوكلو على الـ creating() hook
            // ديال BelongsToCafe يعمرها تلقائياً؛ أوضح وأصلب.)
            AuditLog::create([
                'cafe_id' => $lockedSession->cafe_id,
                'actor_type' => 'user',
                'actor_id' => $staff->id,
                'action' => 'session.checkout',
                'entity_type' => 'table_sessions',
                'entity_id' => $lockedSession->id,
                'before_state' => $beforeState,
                'after_state' => $lockedSession->only(['status', 'checkout_at', 'checkout_by', 'total_final']),
                'reason' => 'Gel du compte et préparation à la facturation (Checkout)',
                'occurred_at' => now(),
            ]);

            return $lockedSession;
        });
    }
}
