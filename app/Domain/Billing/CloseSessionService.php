<?php

namespace App\Domain\Billing;

use App\Domain\Billing\Exceptions\InvalidCheckoutStateException;
use App\Domain\Billing\Exceptions\PendingUnpaidSessionException;
use App\Domain\Billing\Exceptions\TenantMismatchException;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CloseSessionService
{
    /**
     * إغلاق الزيارة النهائي وتحرير الطاولة.
     *
     * القسم 07: "الأداء ما كيحررش الطاولة إذا الناس مازال جالسين" —
     * الإغلاق فعل منفصل وواعي ديال الموظف، ماشي نتيجة تلقائية للأداء.
     *
     * حالتين مسموحتين فقط:
     * 1. paid: كاين Payment صالح لهاد الزيارة.
     * 2. exception_unpaid: Manager فقط، بسبب موثق، بلا أداء وهمي.
     */
    public function close(
        TableSession $session,
        User $staff,
        ?string $unpaidReason = null
    ): TableSession {
        if (app()->bound('current_cafe_id') && (int) app('current_cafe_id') !== (int) $session->cafe_id) {
            throw new TenantMismatchException((int) app('current_cafe_id'), (int) $session->cafe_id);
        }

        return DB::transaction(function () use ($session, $staff, $unpaidReason) {
            // 1. قفل الزيارة
            $lockedSession = TableSession::where('id', $session->id)
                ->where('cafe_id', $session->cafe_id)
                ->lockForUpdate()
                ->firstOrFail();

            // 2. الحالة يجب أن تكون checkout (الحساب مجمّد أولاً — ما كاينش
            // إغلاق مباشر من open بلا تجميد وحساب المجموع)
            if ($lockedSession->status !== TableSession::STATUS_CHECKOUT) {
                throw new InvalidCheckoutStateException($lockedSession->status);
            }

            // 3. فحص وجود Payment صالح لهاد الزيارة
            $payment = Payment::where('cafe_id', $lockedSession->cafe_id)
                ->where('table_session_id', $lockedSession->id)
                ->first();

            $closureReason = TableSession::CLOSURE_PAID;

            if (! $payment) {
                // 4. بلا Payment: خاص استثناء manager صريح وموثق (القسم 08)
                if (! $staff->isManager()) {
                    throw new PendingUnpaidSessionException();
                }

                if (empty($unpaidReason)) {
                    throw new InvalidArgumentException(
                        "L'exception 'impayé' exige un motif explicite (reason) enregistré par le manager."
                    );
                }

                $closureReason = TableSession::CLOSURE_EXCEPTION_UNPAID;
            }

            // 5. تحديث وإغلاق الزيارة
            $beforeState = $lockedSession->only(['status', 'closed_at', 'closed_by', 'closure_reason']);

            $lockedSession->update([
                'status' => TableSession::STATUS_CLOSED,
                'closed_at' => now(),
                'closed_by' => $staff->id,
                'closure_reason' => $closureReason,
            ]);

            // (BUG FIX: active_table_marker هو عمود GENERATED STORED فـ MySQL
            // (كيتحسب تلقائياً من status). Eloquent ما كيعاودش يقرا القيمة
            // المحسوبة من الداتابيز من بعد update() — الـ object فالـ memory
            // كيبقى فيه القيمة القديمة (stale). لازم refresh() صريح باش
            // نرجعو نجيبو القيمة الحقيقية (NULL هنا) قبل ما نرجعو الـ object
            // للمتصل، وإلا أي كود كيتأكد من تحرر الطاولة غادي يتفشل بغلط.)
            $lockedSession->refresh();

            // 6. توثيق العملية فـ audit_logs
            AuditLog::create([
                'cafe_id' => $lockedSession->cafe_id,
                'actor_type' => 'user',
                'actor_id' => $staff->id,
                'action' => 'session.closed',
                'entity_type' => 'table_sessions',
                'entity_id' => $lockedSession->id,
                'before_state' => $beforeState,
                'after_state' => $lockedSession->only(['status', 'closed_at', 'closed_by', 'closure_reason']),
                'reason' => $closureReason === TableSession::CLOSURE_EXCEPTION_UNPAID
                    ? $unpaidReason
                    : 'Fermeture normale après paiement enregistré',
                'occurred_at' => now(),
            ]);

            return $lockedSession;
        });
    }
}
