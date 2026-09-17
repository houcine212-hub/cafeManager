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
    public function recordPayment(
        TableSession $session,
        User $staff,
        string $amount,
        string $method,
        string $idempotencyKey,
        ?string $reference = null
    ): Payment {
        if (app()->bound('current_cafe_id') && (int) app('current_cafe_id') !== (int) $session->cafe_id) {
            throw new TenantMismatchException((int) app('current_cafe_id'), (int) $session->cafe_id);
        }

        if ((int) $staff->cafe_id !== (int) $session->cafe_id || ! $staff->is_active) {
            throw new TenantMismatchException((int) $staff->cafe_id, (int) $session->cafe_id);
        }

        return DB::transaction(function () use ($session, $staff, $amount, $method, $idempotencyKey, $reference) {
            $lockedSession = TableSession::where('id', $session->id)
                ->where('cafe_id', $session->cafe_id)
                ->lockForUpdate()
                ->firstOrFail();

            $existingPayment = Payment::where('cafe_id', $lockedSession->cafe_id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existingPayment) {
                if (! $this->matchesExistingPayload(
                    $existingPayment,
                    $lockedSession->id,
                    $amount,
                    $method,
                    $reference
                )) {
                    throw new IdempotencyConflictException();
                }

                return $existingPayment;
            }

            if ($lockedSession->status !== TableSession::STATUS_CHECKOUT) {
                throw new SessionNotReadyForPaymentException($lockedSession->status);
            }

            $duplicatePayment = Payment::where('cafe_id', $lockedSession->cafe_id)
                ->where('table_session_id', $lockedSession->id)
                ->lockForUpdate()
                ->first();

            if ($duplicatePayment) {
                throw new PaymentAlreadyExistsException();
            }

            $expected = bcadd((string) $lockedSession->total_final, '0.00', 2);
            $received = bcadd($amount, '0.00', 2);

            if (bccomp($expected, $received, 2) !== 0) {
                throw new PaymentAmountMismatchException($expected, $received);
            }

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

    private function matchesExistingPayload(
        Payment $payment,
        int $sessionId,
        string $amount,
        string $method,
        ?string $reference
    ): bool {
        return (int) $payment->table_session_id === $sessionId
            && bccomp((string) $payment->amount, $amount, 2) === 0
            && $payment->method === $method
            && $payment->reference === $reference;
    }
}
