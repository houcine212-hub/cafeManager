<?php

namespace App\Models;

use App\Support\Concerns\BelongsToCafe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleAdjustment extends Model
{
    use BelongsToCafe;

    // الجدول عندو created_at غير، بلا updated_at
    const UPDATED_AT = null;

    protected $fillable = [
        'cafe_id',
        'order_item_id',
        'adjusted_by',
        'amount_delta',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'amount_delta' => 'decimal:2',
        ];
    }

    /**
     * Manager فقط. Rectification de vente قبل الأداء (مثلاً item
     * تسجل served بالغلط) — بلا حذف السطر وبلا refund بلا مال
     * مرجوع فعلي (القسم 09 ديال الكادراج).
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    /**
     * إذا الحساب مؤدى بالفعل ورجع المال فعلاً للزبون بسبب هاد
     * التصحيح، كيتسجل refund مرتبط بيه.
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
