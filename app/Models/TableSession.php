<?php

namespace App\Models;

use App\Support\Concerns\BelongsToCafe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TableSession extends Model
{
    use BelongsToCafe;

    // الحالة التشغيلية (القسم 07 ديال الكادراج)
    public const STATUS_OPEN = 'open';
    public const STATUS_CHECKOUT = 'checkout';
    public const STATUS_CLOSED = 'closed';

    // سبب الإغلاق
    public const CLOSURE_PAID = 'paid';
    public const CLOSURE_EMPTY = 'empty';
    public const CLOSURE_EXCEPTION_UNPAID = 'exception_unpaid';

    protected $fillable = [
        'cafe_id',
        'table_id',
        'opened_by',
        'opened_at',
        'status',
        'checkout_at',
        'checkout_by',
        'total_final',
        'closed_at',
        'closed_by',
        'closure_reason',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'checkout_at' => 'datetime',
            'closed_at' => 'datetime',
            'total_final' => 'decimal:2',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(CafeTable::class, 'table_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function checkoutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checkout_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function guestAccesses(): HasMany
    {
        return $this->hasMany(GuestAccess::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    /**
     * تسوية مالية واحدة صالحة لكل زيارة فـ MVP (D04).
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * الزيارة نشطة (open أو checkout) — القسم 07 ديال الكادراج.
     * ماشي status مستقل يقدر يتناقض مع حالة الطاولة الفعلية.
     */
    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_CHECKOUT], true);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
