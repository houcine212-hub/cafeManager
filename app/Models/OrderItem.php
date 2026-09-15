<?php

namespace App\Models;

use App\Support\Concerns\BelongsToCafe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    use BelongsToCafe;

    protected $fillable = [
        'cafe_id',
        'order_id',
        'product_id',
        'product_name_snapshot',
        'unit_price_snapshot',
        'quantity',
        'line_total',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_snapshot' => 'decimal:2',
            'line_total' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * المنتوج الحالي (للمرجعية فقط). الثمن والاسم المعتمدين هوما
     * unit_price_snapshot / product_name_snapshot — تغيير المنتوج
     * من بعد ما يبدلش السطر التاريخي (القسم 09 ديال الكادراج).
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function saleAdjustments(): HasMany
    {
        return $this->hasMany(SaleAdjustment::class);
    }

    /**
     * حركة الستوك المرتبطة بالبيع ديال هاد السطر (reason = sale).
     * سطر واحد ما كيخلقش جوج حركات بيع (uq_sm_sale_per_item).
     */
    public function saleStockMovement(): HasOne
    {
        return $this->hasOne(StockMovement::class)->where('reason', StockMovement::REASON_SALE);
    }
}
