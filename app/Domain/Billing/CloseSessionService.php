<?php

namespace App\Domain\Billing;

use App\Domain\Billing\Exceptions\InvalidCheckoutStateException;
use App\Domain\Billing\Exceptions\PendingUnpaidSessionException;
use App\Domain\Billing\Exceptions\TenantMismatchException;
use App\Models\AuditLog;
use App\Models\GuestAccess;
use App\Models\Payment;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CloseSessionService
{
    /**
     * Close the visit and release the table.
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
            $lockedSession = TableSession::where('id', $session->id)
                ->where('cafe_id', $session->cafe_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSession->status !== TableSession::STATUS_CHECKOUT) {
                throw new InvalidCheckoutStateException($lockedSession->status);
            }

            $payment = Payment::where('cafe_id', $lockedSession->cafe_id)
                ->where('table_session_id', $lockedSession->id)
                ->first();

            $closureReason = TableSession::CLOSURE_PAID;

            if (! $payment) {
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

            $beforeState = $lockedSession->only(['status', 'closed_at', 'closed_by', 'closure_reason']);

            $lockedSession->update([
                'status' => TableSession::STATUS_CLOSED,
                'closed_at' => now(),
                'closed_by' => $staff->id,
                'closure_reason' => $closureReason,
            ]);

            // Closing a visit invalidates every device access for that visit.
            GuestAccess::where('table_session_id', $lockedSession->id)
                ->whereIn('status', [GuestAccess::STATUS_PENDING, GuestAccess::STATUS_APPROVED])
                ->update([
                    'status' => GuestAccess::STATUS_REVOKED,
                    'revoked_at' => now(),
                ]);

            // Refresh generated columns before returning the session instance.
            $lockedSession->refresh();

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
