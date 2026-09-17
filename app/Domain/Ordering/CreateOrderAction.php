<?php

namespace App\Domain\Ordering;

use App\Domain\Ordering\Exceptions\IdempotencyConflictException;
use App\Domain\Ordering\Exceptions\ProductOutOfStockException;
use App\Domain\Ordering\Exceptions\ProductUnavailableException;
use App\Domain\Ordering\Exceptions\SessionNotAcceptingOrdersException;
use App\Domain\Ordering\Exceptions\UnauthorizedGuestAccessException;
use App\Models\AuditLog;
use App\Models\GuestAccess;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\TableSession;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CreateOrderAction
{
    public function execute(
        int $cafeId,
        int $sessionId,
        array $items,
        string $idempotencyKey,
        string $source,
        ?int $guestAccessId = null,
        ?int $userId = null,
        ?string $notes = null
    ): Order {
        if (app()->bound('current_cafe_id') && (int) app('current_cafe_id') !== $cafeId) {
            throw new RuntimeException(sprintf(
                'Tenant mismatch: Action cafeId (%d) does not match active context (%d).',
                $cafeId,
                app('current_cafe_id')
            ));
        }

        $this->validateActor($source, $guestAccessId, $userId);
        $this->validateItemsPayload($items);

        return DB::transaction(function () use (
            $cafeId,
            $sessionId,
            $items,
            $idempotencyKey,
            $source,
            $guestAccessId,
            $userId,
            $notes
        ) {
            $session = TableSession::where('id', $sessionId)
                ->where('cafe_id', $cafeId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $session->isOpen()) {
                throw new SessionNotAcceptingOrdersException($session->status);
            }

            $existingOrder = Order::where('cafe_id', $cafeId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existingOrder) {
                if (! $this->matchesExistingPayload(
                    $existingOrder,
                    $sessionId,
                    $items,
                    $source,
                    $guestAccessId,
                    $userId,
                    $notes
                )) {
                    throw new IdempotencyConflictException();
                }

                return $existingOrder->load('orderItems');
            }

            if ($source === 'qr') {
                $guestAccess = GuestAccess::where('id', $guestAccessId)
                    ->where('cafe_id', $cafeId)
                    ->where('table_session_id', $sessionId)
                    ->lockForUpdate()
                    ->first();

                if (! $guestAccess || ! $guestAccess->isApproved()) {
                    throw new UnauthorizedGuestAccessException();
                }
            }

            $itemCollection = collect($items)->sortBy('product_id')->values();
            $productIds = $itemCollection->pluck('product_id')->unique()->toArray();

            $products = Product::whereIn('id', $productIds)
                ->where('cafe_id', $cafeId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($itemCollection as $line) {
                $productId = $line['product_id'];
                $product = $products->get($productId);

                if (! $product || ! $product->is_available) {
                    $name = $product ? $product->name : "ID #{$productId}";
                    throw new ProductUnavailableException($name);
                }
            }

            $order = Order::create([
                'cafe_id' => $cafeId,
                'table_session_id' => $sessionId,
                'source' => $source,
                'guest_access_id' => $guestAccessId,
                'created_by_user_id' => $userId,
                'status' => 'new',
                'idempotency_key' => $idempotencyKey,
                'notes' => $notes,
            ]);

            foreach ($itemCollection as $line) {
                $productId = $line['product_id'];
                $quantity = (int) $line['quantity'];
                $product = $products->get($productId);

                if ($product->track_stock) {
                    $affected = DB::table('products')
                        ->where('id', $productId)
                        ->where('cafe_id', $cafeId)
                        ->where('track_stock', true)
                        ->where('stock_quantity', '>=', $quantity)
                        ->decrement('stock_quantity', $quantity);

                    if ($affected === 0) {
                        throw new ProductOutOfStockException($product->name);
                    }

                    $product->stock_quantity -= $quantity;
                }

                $lineTotal = bcmul((string) $product->price, (string) $quantity, 2);

                $orderItem = OrderItem::create([
                    'cafe_id' => $cafeId,
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name_snapshot' => $product->name,
                    'unit_price_snapshot' => $product->price,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                    'note' => $line['note'] ?? null,
                ]);

                if ($product->track_stock) {
                    StockMovement::create([
                        'cafe_id' => $cafeId,
                        'product_id' => $product->id,
                        'delta' => -$quantity,
                        'quantity_after' => $product->stock_quantity,
                        'reason' => 'sale',
                        'order_item_id' => $orderItem->id,
                        'actor_type' => 'system',
                        'actor_id' => null,
                        'note' => "Vente commande #{$order->id}",
                        'occurred_at' => now(),
                    ]);
                }
            }

            AuditLog::create([
                'cafe_id' => $cafeId,
                'actor_type' => $source === 'staff' ? 'user' : 'guest',
                'actor_id' => $source === 'staff' ? $userId : $guestAccessId,
                'action' => 'order.created',
                'entity_type' => 'orders',
                'entity_id' => $order->id,
                'before_state' => null,
                'after_state' => $order->toArray(),
                'reason' => 'Nouvelle commande créée',
                'occurred_at' => now(),
            ]);

            return $order->load('orderItems');
        });
    }

    private function validateItemsPayload(array $items): void
    {
        if (empty($items)) {
            throw new InvalidArgumentException("La commande doit contenir au moins un article.");
        }

        foreach ($items as $line) {
            if (! isset($line['product_id'], $line['quantity'])) {
                throw new InvalidArgumentException("Chaque article doit spécifier un product_id et une quantity.");
            }

            $qty = $line['quantity'];
            if (! is_int($qty) || $qty < 1 || $qty > 50) {
                throw new InvalidArgumentException("La quantité de chaque article doit être un entier entre 1 et 50.");
            }
        }
    }

    private function validateActor(string $source, ?int $guestAccessId, ?int $userId): void
    {
        if ($source === 'qr' && (empty($guestAccessId) || ! empty($userId))) {
            throw new InvalidArgumentException("Une commande QR doit avoir un guest_access_id et aucun user_id.");
        }

        if ($source === 'staff' && (empty($userId) || ! empty($guestAccessId))) {
            throw new InvalidArgumentException("Une commande STAFF doit avoir un user_id et aucun guest_access_id.");
        }

        if (! in_array($source, ['qr', 'staff'], true)) {
            throw new InvalidArgumentException("La source de la commande doit être 'qr' ou 'staff'.");
        }
    }

    private function matchesExistingPayload(
        Order $order,
        int $sessionId,
        array $newItems,
        string $source,
        ?int $guestAccessId,
        ?int $userId,
        ?string $notes
    ): bool {
        if ((int) $order->table_session_id !== $sessionId
            || $order->source !== $source
            || ($order->guest_access_id === null ? null : (int) $order->guest_access_id) !== $guestAccessId
            || ($order->created_by_user_id === null ? null : (int) $order->created_by_user_id) !== $userId
            || $order->notes !== $notes
        ) {
            return false;
        }

        $existing = $order->orderItems()
            ->get(['product_id', 'quantity', 'note'])
            ->sortBy('product_id')
            ->values()
            ->map(fn ($item) => [
                'product_id' => (int) $item->product_id,
                'quantity' => (int) $item->quantity,
                'note' => $item->note,
            ])
            ->toArray();

        $incoming = collect($newItems)
            ->sortBy('product_id')
            ->values()
            ->map(fn ($item) => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
                'note' => $item['note'] ?? null,
            ])
            ->toArray();

        return $existing === $incoming;
    }
}
