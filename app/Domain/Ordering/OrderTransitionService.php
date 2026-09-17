<?php

namespace App\Domain\Ordering;

use App\Domain\Ordering\Exceptions\InvalidOrderTransitionException;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class OrderTransitionService
{
    protected array $allowedTransitions = [
        'new' => ['accepted', 'cancelled'],
        'accepted' => ['preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['served', 'cancelled'],
        'served' => [],
        'cancelled' => [],
    ];

    public function transition(
        Order $order,
        string $newStatus,
        User $actor,
        ?string $reason = null,
        ?string $stockResolution = null
    ): Order {
        if ((int) $actor->cafe_id !== (int) $order->cafe_id || ! $actor->is_active) {
            throw new RuntimeException('The actor must be an active user from the order cafe.');
        }

        return DB::transaction(function () use ($order, $newStatus, $actor, $reason, $stockResolution) {
            $lockedOrder = Order::where('id', $order->id)
                ->where('cafe_id', $order->cafe_id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = $lockedOrder->status;
            $allowed = $this->allowedTransitions[$currentStatus] ?? [];

            if (! in_array($newStatus, $allowed, true)) {
                throw new InvalidOrderTransitionException($currentStatus, $newStatus);
            }

            if ($newStatus === 'cancelled') {
                $this->handleCancellationStock($lockedOrder, $actor, $stockResolution);
            }

            $beforeState = ['status' => $currentStatus];
            $lockedOrder->status = $newStatus;
            $lockedOrder->save();

            AuditLog::create([
                'cafe_id' => $lockedOrder->cafe_id,
                'actor_type' => 'user',
                'actor_id' => $actor->id,
                'action' => 'order.status_changed',
                'entity_type' => 'orders',
                'entity_id' => $lockedOrder->id,
                'before_state' => $beforeState,
                'after_state' => ['status' => $newStatus],
                'reason' => $reason,
                'occurred_at' => now(),
            ]);

            return $lockedOrder;
        });
    }

    protected function handleCancellationStock(Order $order, User $actor, ?string $stockResolution): void
    {
        $order->loadMissing('orderItems.product');

        foreach ($order->orderItems as $item) {
            $product = $item->product;

            if (! $product || ! $product->track_stock) {
                continue;
            }

            if (! in_array($stockResolution, ['restock', 'waste'], true)) {
                throw new InvalidArgumentException(
                    "L'annulation d'une commande contenant des articles suivis en stock exige une résolution ('restock' ou 'waste')."
                );
            }

            if ($stockResolution === 'restock') {
                DB::table('products')
                    ->where('id', $product->id)
                    ->where('cafe_id', $order->cafe_id)
                    ->increment('stock_quantity', $item->quantity);

                $product->refresh();

                StockMovement::create([
                    'cafe_id' => $order->cafe_id,
                    'product_id' => $product->id,
                    'delta' => $item->quantity,
                    'quantity_after' => $product->stock_quantity,
                    'reason' => 'restock',
                    'order_item_id' => null,
                    'actor_type' => 'user',
                    'actor_id' => $actor->id,
                    'note' => "Restock suite annulation commande #{$order->id}",
                    'occurred_at' => now(),
                ]);

                continue;
            }

            // The sale movement already reduced stock when the order was created.
            // Reclassify that movement as waste instead of recording a second decrease.
            $saleMovement = StockMovement::where('cafe_id', $order->cafe_id)
                ->where('product_id', $product->id)
                ->where('order_item_id', $item->id)
                ->where('reason', 'sale')
                ->lockForUpdate()
                ->first();

            if (! $saleMovement) {
                throw new RuntimeException(
                    "Stock sale movement missing for order item #{$item->id}."
                );
            }

            $saleMovement->update([
                'reason' => 'waste',
                'actor_type' => 'user',
                'actor_id' => $actor->id,
                'note' => "Perte (waste) suite annulation commande #{$order->id}",
                'occurred_at' => now(),
            ]);
        }
    }
}
