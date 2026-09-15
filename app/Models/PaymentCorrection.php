<?php

namespace App\Models;

use App\Support\Concerns\BelongsToCafe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentCorrection extends Model
{
    use BelongsToCafe;

    // الجدول عندو created_at غير، بلا updated_at
    const UPDATED_AT = null;

    protected $fillable = [
        'cafe_id',
        'payment_id',
        'corrected_by',
        'amount_before',
        'amount_after',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'amount_before' => 'decimal:2',
            'amount_after' => 'decimal:2',
        ];
    }

    /**
     * تصحيح إدخال ديال أداء مسّجل بالغلط — ما كيّدعيش أن المال
     * خرج فعلاً من الصندوق (القسم 09 ديال الكادراج، يختلف عن Refund).
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
