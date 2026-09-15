<?php

namespace App\Models;

use App\Support\Concerns\BelongsToCafe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use BelongsToCafe;

    // الجدول عندو created_at غير (useCurrent)، بلا updated_at — تصحيح ما كيتبدلش
    const UPDATED_AT = null;

    public const METHOD_CASH = 'cash';
    public const METHOD_CARD = 'card';
    public const METHOD_OTHER = 'other';

    protected $fillable = [
        'cafe_id',
        'payment_id',
        'sale_adjustment_id',
        'amount',
        'method',
        'processed_by',
        'reference',
        'reason',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'refunded_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * إذا الـ refund مرتبط بتصحيح بيع (sale_adjustment)، هذا كيمنع
     * إنقاص المبيعات مرتين — القسم 09 ديال الكادراج.
     */
    public function saleAdjustment(): BelongsTo
    {
        return $this->belongsTo(SaleAdjustment::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
