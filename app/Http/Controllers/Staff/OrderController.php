<?php

namespace App\Http\Controllers\Staff;

use App\Domain\Ordering\CreateOrderAction;
use App\Domain\Ordering\OrderTransitionService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'since' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ]);

        $query = Order::query()
            ->with([
                'orderItems.product',
                'tableSession.table',
            ])
            ->latest('id');

        if (array_key_exists('since', $validated)) {
            $since = (int) $validated['since'];

            $query->where(
                'updated_at',
                '>',
                date('Y-m-d H:i:s', $since)
            );
        } else {
            $query->whereIn('status', [
                'new',
                'accepted',
                'preparing',
                'ready',
            ]);
        }

        return response()->json([
            'server_time' => now()->timestamp,
            'orders' => $query->get(),
        ]);
    }

    public function store(
        Request $request,
        CreateOrderAction $action
    ): JsonResponse {
        $validated = $request->validate([
            'session_id' => [
                'required',
                'integer',
                'min:1',
            ],
            'items' => [
                'required',
                'array',
                'min:1',
            ],
            'items.*.product_id' => [
                'required',
                'integer',
                'min:1',
            ],
            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
                'max:50',
            ],
            'items.*.note' => [
                'nullable',
                'string',
                'max:255',
            ],
            'idempotency_key' => [
                'nullable',
                'string',
                'max:64',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
        ]);

        $idempotencyKey = $validated['idempotency_key']
            ?? (string) Str::uuid();

        $order = $action->execute(
            cafeId: (int) app('current_cafe_id'),
            sessionId: (int) $validated['session_id'],
            items: $validated['items'],
            idempotencyKey: $idempotencyKey,
            source: 'staff',
            guestAccessId: null,
            userId: $request->user()->id,
            notes: $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Commande enregistrée.',
            'order' => $order,
        ], 201);
    }

    public function updateStatus(
        Request $request,
        int|string $orderId,
        OrderTransitionService $service
    ): JsonResponse {
        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                'in:new,accepted,preparing,ready,served,cancelled',
            ],
            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'stock_resolution' => [
                'nullable',
                'in:restock,waste',
            ],
        ]);

        $order = Order::findOrFail($orderId);

        $updatedOrder = $service->transition(
            order: $order,
            newStatus: $validated['status'],
            actor: $request->user(),
            reason: $validated['reason'] ?? null,
            stockResolution: $validated['stock_resolution'] ?? null
        );

        return response()->json([
            'message' => 'Statut mis à jour.',
            'order' => $updatedOrder,
        ]);
    }
}
