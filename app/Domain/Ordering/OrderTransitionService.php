<?php

namespace App\Domain\Ordering;

use App\Domain\Ordering\Exceptions\InvalidOrderTransitionException;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderTransitionService
{
    /**
     * الحالات المسموح بالانتقال إليها وفق الدستور (Section 11)
     */
    protected array $allowedTransitions = [
        'new' => ['accepted', 'cancelled'],
        'accepted' => ['preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['served', 'cancelled'],
        'served' => [],    // نهائية: مستحيل الإلغاء بعد التسليم
        'cancelled' => [], // نهائية
    ];

    /**
     * تنفيذ تغيير حالة الطلب وتوثيقه فـ AuditLog ومعالجة ستوك D15
     */
    public function transition(
        Order $order,
        string $newStatus,
        User $actor,
        ?string $reason = null,
        ?string $stockResolution = null
    ): Order {
        return DB::transaction(function () use ($order, $newStatus, $actor, $reason, $stockResolution) {
            // 1. قفل الطلب فالداتابيز
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();
            $currentStatus = $lockedOrder->status;

            // 2. التحقق الاستباقي وتفرگيع Exception المحترفة ديالنا
            $allowed = $this->allowedTransitions[$currentStatus] ?? [];
            if (! in_array($newStatus, $allowed, true)) {
                throw new InvalidOrderTransitionException($currentStatus, $newStatus);
            }

            // 3. معالجة الستوك D15 إذا تم إلغاء الطلب
            if ($newStatus === 'cancelled') {
                $this->handleCancellationStock($lockedOrder, $actor, $stockResolution);
            }

            // 4. تحديث الحالة
            $beforeState = ['status' => $currentStatus];
            $lockedOrder->status = $newStatus;
            $lockedOrder->save();

            // 5. توثيق العملية فـ audit_logs
            AuditLog::create([
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

    /**
     * معالجة استرجاع أو إتلاف السلعة المعلبة (D15) عند الإلغاء
     */
    protected function handleCancellationStock(Order $order, User $actor, ?string $stockResolution): void
    {
        $order->loadMissing('orderItems.product');

        foreach ($order->orderItems as $item) {
            $product = $item->product;

            if ($product && $product->track_stock) {
                if (! in_array($stockResolution, ['restock', 'waste'], true)) {
                    throw new InvalidArgumentException(
                        "L'annulation d'une commande contenant des articles suivis en stock exige une résolution ('restock' ou 'waste')."
                    );
                }

                // Restock: السلعة صالحة وترجع للثلاجة
                if ($stockResolution === 'restock') {
                    DB::table('products')->where('id', $product->id)->increment('stock_quantity', $item->quantity);
                    $product->refresh();

                    StockMovement::create([
                        'product_id' => $product->id,
                        'delta' => $item->quantity, // دخول
                        'quantity_after' => $product->stock_quantity,
                        'reason' => 'restock',
                        'actor_type' => 'user',
                        'actor_id' => $actor->id,
                        'note' => "Restock suite annulation commande #{$order->id}",
                        'occurred_at' => now(),
                    ]);
                }
                // Waste: ضاعت وتلاحت
                elseif ($stockResolution === 'waste') {
                    StockMovement::create([
                        'product_id' => $product->id,
                        'delta' => -$item->quantity, // كتبقى ضايعة فالداتابيز للكونطابيليتي
                        'quantity_after' => $product->stock_quantity,
                        'reason' => 'waste',
                        'actor_type' => 'user',
                        'actor_id' => $actor->id,
                        'note' => "Perte (waste) suite annulation commande #{$order->id}",
                        'occurred_at' => now(),
                    ]);
                }
            }
        }
    }
}
