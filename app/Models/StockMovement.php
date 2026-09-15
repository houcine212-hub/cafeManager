<?php

namespace App\Models;

use App\Support\Concerns\BelongsToCafe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use BelongsToCafe;

    // الجدول عندو occurred_at غير، بلا created_at/updated_at
    public $timestamps = false;

    public const REASON_SALE = 'sale';
    public const REASON_RESTOCK = 'restock';
    public const REASON_WASTE = 'waste';
    public const REASON_CORRECTION = 'correction';
    public const REASON_INITIAL = 'initial';

    public const ACTOR_USER = 'user';
    public const ACTOR_SYSTEM = 'system';

    protected $fillable = [
        'cafe_id',
        'product_id',
        'delta',
        'quantity_after',
        'reason',
        'order_item_id',
        'actor_type',
        'actor_id',
        'note',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'delta' => 'integer',
            'quantity_after' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * موجود غير إذا reason = sale — السطر اللي خلق البيع (D15).
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * null إذا actor_type = system (مثلاً حركة sale تلقائية).
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
