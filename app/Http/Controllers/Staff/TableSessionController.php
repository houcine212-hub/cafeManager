<?php

namespace App\Http\Controllers\Staff;

use App\Domain\Billing\CheckoutService;
use App\Domain\Billing\CloseSessionService;
use App\Http\Controllers\Controller;
use App\Models\CafeTable;
use App\Models\TableSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableSessionController extends Controller
{
    public function open(
        Request $request,
        int|string $tableId
    ): JsonResponse {
        $table = CafeTable::query()
            ->where('is_active', true)
            ->findOrFail($tableId);

        $session = TableSession::create([
            'cafe_id' => $table->cafe_id,
            'table_id' => $table->id,
            'opened_by' => $request->user()->id,
            'status' => TableSession::STATUS_OPEN,
        ]);

        return response()->json([
            'message' => 'Table ouverte avec succès.',
            'session' => $session,
        ], 201);
    }

    public function checkout(
        Request $request,
        int|string $sessionId,
        CheckoutService $checkoutService
    ): JsonResponse {
        $session = TableSession::findOrFail($sessionId);

        $checkoutSession = $checkoutService->checkout(
            $session,
            $request->user()
        );

        return response()->json([
            'message' => 'Addition figée avec succès.',
            'total_final' => $checkoutSession->total_final,
            'session' => $checkoutSession,
        ]);
    }

    public function close(
        Request $request,
        int|string $sessionId,
        CloseSessionService $closeService
    ): JsonResponse {
        $data = $request->validate([
            'unpaid_reason' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $session = TableSession::findOrFail($sessionId);

        $closedSession = $closeService->close(
            $session,
            $request->user(),
            $data['unpaid_reason'] ?? null
        );

        return response()->json([
            'message' => 'Table libérée avec succès.',
            'session' => $closedSession,
        ]);
    }
}