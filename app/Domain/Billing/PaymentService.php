<?php

namespace App\Domain\Billing;

use App\Domain\Billing\Exceptions\IdempotencyConflictException;
use App\Domain\Billing\Exceptions\PaymentAlreadyExistsException;
use App\Domain\Billing\Exceptions\PaymentAmountMismatchException;
use App\Domain\Billing\Exceptions\SessionNotReadyForPaymentException;
use App\Domain\Billing\Exceptions\TenantMismatchException;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * تسجيل الأداء الفعلي لزيارة مجمّدة (بعد Checkout).
     *
     * D04: تسوية واحدة صالحة لكل زيارة فـ MVP. القرار هنا ما كيسدش
     * الزيارة (closure منفصل عمداً — القسم 07: "الأداء ما كيحررش
     * الطاولة إذا الناس مازال جالسين").
     */
    public function recordPayment(
        TableSession $session,
        User $staff,
        string $amount,
        string $method,
        string $idempotencyKey,
        ?string $reference = null
    ): Payment {
        // 0. فحص تطابق الـ Tenant قبل الدخول للـ Transaction
        if (app()->bound('current_cafe_id') && (int) app('current_cafe_id') !== (int) $session->cafe_id) {
            throw new TenantMismatchException((int) app('current_cafe_id'), (int) $session->cafe_id);
        }

        return DB::transaction(function () use ($session, $staff, $amount, $method, $idempotencyKey, $reference) {
            // 1. قفل الزيارة لمنع أي أداء متزامن على نفس الزيارة
            $lockedSession = TableSession::where('id', $session->id)
                ->where('cafe_id', $session->cafe_id)
                ->lockForUpdate()
                ->firstOrFail();

            // 2. التحقق من الـ Idempotency أولاً (retry بنفس المفتاح يرجع نفس النتيجة)
            $existingPayment = Payment::where('cafe_id', $lockedSession->cafe_id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existingPayment) {
                if (! $this->matchesExistingPayload($existingPayment, $amount, $method, $reference)) {
                    throw new IdempotencyConflictException();
                }

                return $existingPayment;
            }

            // 3. الحالة يجب أن تكون checkout (الحساب مجمّد قبل الأداء — D02)
            if ($lockedSession->status !== TableSession::STATUS_CHECKOUT) {
                throw new SessionNotReadyForPaymentException($lockedSession->status);
            }

            // 4. فحص D04: تسوية واحدة صالحة لكل زيارة (قفل الصف باش نمنعو Race)
            $duplicatePayment = Payment::where('cafe_id', $lockedSession->cafe_id)
                ->where('table_session_id', $lockedSession->id)
                ->lockForUpdate()
                ->first();

            if ($duplicatePayment) {
                throw new PaymentAlreadyExistsException();
            }

            // 5. المبلغ المدفوع خاصو يطابق المبلغ المجمّد بالضبط (bccomp لدقة DECIMAL)
            $expected = bcadd((string) $lockedSession->total_final, '0.00', 2);
            $received = bcadd($amount, '0.00', 2);

            if (bccomp($expected, $received, 2) !== 0) {
                throw new PaymentAmountMismatchException($expected, $received);
            }

            // 6. إنشاء سجل الأداء
            $payment = Payment::create([
                'cafe_id' => $lockedSession->cafe_id,
                'table_session_id' => $lockedSession->id,
                'amount' => $received,
                'method' => $method,
                'received_by_user_id' => $staff->id,
                'reference' => $reference,
                'idempotency_key' => $idempotencyKey,
                'paid_at' => now(),
            ]);

            // 7. توثيق العملية فـ audit_logs
            AuditLog::create([
                'cafe_id' => $lockedSession->cafe_id,
                'actor_type' => 'user',
                'actor_id' => $staff->id,
                'action' => 'payment.recorded',
                'entity_type' => 'payments',
                'entity_id' => $payment->id,
                'before_state' => null,
                'after_state' => $payment->toArray(),
                'reason' => 'Encaissement du montant dû après gel du compte (checkout)',
                'occurred_at' => now(),
            ]);

            return $payment;
        });
    }

    /**
     * مقارنة دقيقة للـ Payload عند تكرار نفس الـ Idempotency Key
     */
    private function matchesExistingPayload(Payment $payment, string $amount, string $method, ?string $reference): bool
    {
        return bccomp((string) $payment->amount, $amount, 2) === 0
            && $payment->method === $method
            && $payment->reference === $reference;
    }
}
