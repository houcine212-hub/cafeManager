<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\GuestAccess;
use App\Models\TableSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GuestAccessController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => [
                'nullable',
                'in:pending,approved,revoked,expired',
            ],
        ]);

        $status = $validated['status'] ?? GuestAccess::STATUS_PENDING;

        $accesses = GuestAccess::query()
            ->where('status', $status)
            ->whereHas('tableSession', function ($query) {
                $query->where('status', TableSession::STATUS_OPEN);
            })
            ->with('tableSession.table')
            ->latest('requested_at')
            ->get()
            ->map(fn (GuestAccess $access) => $this->present($access));

        return response()->json([
            'guest_accesses' => $accesses,
        ]);
    }

    public function approve(Request $request, int|string $guestAccessId): JsonResponse
    {
        return DB::transaction(function () use ($request, $guestAccessId) {
            $access = GuestAccess::where('id', $guestAccessId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($access->status === GuestAccess::STATUS_APPROVED) {
                return response()->json([
                    'message' => 'Accès déjà approuvé.',
                    'guest_access' => $this->present($access->load('tableSession.table')),
                ]);
            }

            if ($access->status !== GuestAccess::STATUS_PENDING) {
                return response()->json([
                    'error' => 'access_not_pending',
                    'message' => 'Cet accès ne peut plus être approuvé.',
                ], 409);
            }

            $session = TableSession::where('id', $access->table_session_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($session->status !== TableSession::STATUS_OPEN) {
                return response()->json([
                    'error' => 'session_not_open',
                    'message' => 'La session de cette table n’est plus ouverte.',
                ], 409);
            }

            $beforeState = $access->only(['status', 'approved_by', 'approved_at', 'revoked_at']);

            $access->update([
                'status' => GuestAccess::STATUS_APPROVED,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'revoked_at' => null,
            ]);

            AuditLog::create([
                'cafe_id' => $access->cafe_id,
                'actor_type' => AuditLog::ACTOR_USER,
                'actor_id' => $request->user()->id,
                'action' => 'guest_access.approved',
                'entity_type' => 'guest_accesses',
                'entity_id' => $access->id,
                'before_state' => $beforeState,
                'after_state' => $access->only(['status', 'approved_by', 'approved_at', 'revoked_at']),
                'reason' => 'Approbation par le personnel.',
                'occurred_at' => now(),
            ]);

            return response()->json([
                'message' => 'Accès invité approuvé.',
                'guest_access' => $this->present($access->load('tableSession.table')),
            ]);
        });
    }

    public function revoke(Request $request, int|string $guestAccessId): JsonResponse
    {
        return DB::transaction(function () use ($request, $guestAccessId) {
            $access = GuestAccess::where('id', $guestAccessId)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($access->status, [GuestAccess::STATUS_REVOKED, GuestAccess::STATUS_EXPIRED], true)) {
                return response()->json([
                    'message' => 'Accès déjà inactif.',
                    'guest_access' => $this->present($access->load('tableSession.table')),
                ]);
            }

            $beforeState = $access->only(['status', 'approved_by', 'approved_at', 'revoked_at']);

            $access->update([
                'status' => GuestAccess::STATUS_REVOKED,
                'revoked_at' => now(),
            ]);

            AuditLog::create([
                'cafe_id' => $access->cafe_id,
                'actor_type' => AuditLog::ACTOR_USER,
                'actor_id' => $request->user()->id,
                'action' => 'guest_access.revoked',
                'entity_type' => 'guest_accesses',
                'entity_id' => $access->id,
                'before_state' => $beforeState,
                'after_state' => $access->only(['status', 'approved_by', 'approved_at', 'revoked_at']),
                'reason' => 'Révocation par le personnel.',
                'occurred_at' => now(),
            ]);

            return response()->json([
                'message' => 'Accès invité révoqué.',
                'guest_access' => $this->present($access->load('tableSession.table')),
            ]);
        });
    }

    private function present(GuestAccess $access): array
    {
        return [
            'id' => $access->id,
            'status' => $access->status,
            'table_session_id' => $access->table_session_id,
            'requested_at' => $access->requested_at,
            'approved_at' => $access->approved_at,
            'revoked_at' => $access->revoked_at,
            'table' => [
                'id' => $access->tableSession?->table?->id,
                'label' => $access->tableSession?->table?->label,
            ],
        ];
    }
}
