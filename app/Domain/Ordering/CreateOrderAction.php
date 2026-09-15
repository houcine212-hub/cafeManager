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
        // 1. فحص تطابق سياق الـ Tenant فالبداية لمنع أي خلط صامت
        if (app()->bound('current_cafe_id') && (int) app('current_cafe_id') !== $cafeId) {
            throw new RuntimeException(sprintf(
                'Tenant mismatch: Action cafeId (%d) does not match active context (%d).',
                $cafeId,
                app('current_cafe_id')
            ));
        }

        // 2. فحص هوية الفاعل وفق الدستور (Section 13)
        $this->validateActor($source, $guestAccessId, $userId);

        // 3. فحص صارم للكميات لمنع القيم السالبة وتضخيم الستوك
        $this->validateItemsPayload($items);

        return DB::transaction(function () use (
            $cafeId, $sessionId, $items, $idempotencyKey, $source, $guestAccessId, $userId, $notes
        ) {
            // 4. قفل الجلسة أولاً والتأكد من أنها مفتوحة (Status = OPEN)
            $session = TableSession::where('id', $sessionId)
                ->where('cafe_id', $cafeId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $session->isOpen()) {
                throw new SessionNotAcceptingOrdersException($session->status);
            }

            // 5. التحقق من الـ Idempotency أولاً (Section 08 & 14)
            // مهم: retry بنفس المحتوى يجب أن يرجع نفس النتيجة بلا أي شرط إضافي
            // (بما فيه حالة guest_access)، حتى لو تغيّرت صلاحية الزبون بعد
            // التثبيت الأصلي. لهذا هاد الفحص قبل فحص guest_access.
            $existingOrder = Order::where('cafe_id', $cafeId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existingOrder) {
                if (! $this->matchesExistingPayload($existingOrder, $items)) {
                    throw new IdempotencyConflictException();
                }

                return $existingOrder->load('orderItems');
            }

            // 6. إذا كان الطلب من QR (إنشاء جديد)، نتحقق أن تصريح الزبون
            // نشط ومعتمد (Approved) لنفس الجلسة. القفل هنا لتفادي تغيّر
            // الحالة (revoke) بين الفحص والاستعمال داخل نفس الـ transaction.
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

            // 7. ترتيب المنتجات تصاعدياً حسب id لمنع الـ Deadlock (Test T21)
            $itemCollection = collect($items)->sortBy('product_id')->values();
            $productIds = $itemCollection->pluck('product_id')->unique()->toArray();

            // قفل المنتجات المطلوبة وإعادة قراءة الثمن والتوفر الحقيقي من الداتابيز
            $products = Product::whereIn('id', $productIds)
                ->where('cafe_id', $cafeId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // 8. فحص توفر جميع المنتجات
            foreach ($itemCollection as $line) {
                $productId = $line['product_id'];
                /** @var Product|null $product */
                $product = $products->get($productId);

                if (! $product || ! $product->is_available) {
                    $name = $product ? $product->name : "ID #{$productId}";
                    throw new ProductUnavailableException($name);
                }
            }

            // 9. إنشاء صف الطلب (Order)
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

            // 10. معالجة أسطر الطلب ونقصان الستوك الذري D15
            foreach ($itemCollection as $line) {
                $productId = $line['product_id'];
                $quantity = (int) $line['quantity'];
                /** @var Product $product */
                $product = $products->get($productId);

                // أ. النقصان الذري للمنتجات المتتبعة بالستوك D15
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

                // ب. حساب line_total باستعمال bcmul بدقة DECIMAL (بلا Float)
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

                // ج. تسجيل حركة البيع فـ stock_movements (D15)
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

            // 11. توثيق العملية فـ audit_logs مع تحديد هوية الفاعل بدقة
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

    /**
     * فحص مدخلات السلعة والكميات (منع القيم السالبة والصفرية)
     */
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

    /**
     * التحقق من فاعل الطلب وفق الدستور (Section 13)
     */
    private function validateActor(string $source, ?int $guestAccessId, ?int $userId): void
    {
        if ($source === 'qr' && (empty($guestAccessId) || ! empty($userId))) {
            throw new InvalidArgumentException("Une commande QR doit avoir un guest_access_id et aucun user_id.");
        }

        if ($source === 'staff' && (empty($userId) || ! empty($guestAccessId))) {
            throw new InvalidArgumentException("Une commande STAFF doit avoir un user_id et aucun guest_access_id.");
        }
    }

    /**
     * مقارنة دقيقة للـ Payload عند تكرار نفس الـ Idempotency Key
     */
    private function matchesExistingPayload(Order $order, array $newItems): bool
    {
        $existing = $order->orderItems()
            ->get(['product_id', 'quantity'])
            ->sortBy('product_id')
            ->values()
            ->map(fn ($i) => ['product_id' => (int) $i->product_id, 'quantity' => (int) $i->quantity])
            ->toArray();

        $incoming = collect($newItems)
            ->sortBy('product_id')
            ->values()
            ->map(fn ($i) => ['product_id' => (int) $i['product_id'], 'quantity' => (int) $i['quantity']])
            ->toArray();

        return $existing === $incoming;
    }
}
