<?php

namespace App\Models;

use App\Support\Concerns\BelongsToCafe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use BelongsToCafe;

    public const SOURCE_QR = 'qr';
    public const SOURCE_STAFF = 'staff';

    public const STATUS_NEW = 'new';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_READY = 'ready';
    public const STATUS_SERVED = 'served';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * الانتقال الخطي المسموح بلا رجوع عشوائي — القسم 07 ديال الكادراج.
     * NEW -> ACCEPTED -> PREPARING -> READY -> SERVED
     */
    private const FORWARD_FLOW = [
        self::STATUS_NEW => self::STATUS_ACCEPTED,
        self::STATUS_ACCEPTED => self::STATUS_PREPARING,
        self::STATUS_PREPARING => self::STATUS_READY,
        self::STATUS_READY => self::STATUS_SERVED,
    ];

    /**
     * CANCELLED ممكن ينتقل ليه من أي حالة قبل served، بصلاحية وسبب.
     * served ما كتولّيش cancelled (القسم 07): البيع تصحح بـ sale_adjustment، ماشي حذف.
     */
    private const CANCELLABLE_FROM = [
        self::STATUS_NEW,
        self::STATUS_ACCEPTED,
        self::STATUS_PREPARING,
        self::STATUS_READY,
    ];

    protected $fillable = [
        'cafe_id',
        'table_session_id',
        'source',
        'guest_access_id',
        'created_by_user_id',
        'status',
        'idempotency_key',
        'notes',
    ];

    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    public function guestAccess(): BelongsTo
    {
        return $this->belongsTo(GuestAccess::class);
    }

    /**
     * الموظف اللي دخل الطلب يدوياً (source = staff). null إذا source = qr.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * الحالات المسموح ينتقل ليها الطلب من الحالة الحالية ديالو.
     * كتستعمل مباشرة فـ staff/partials/order-card.blade.php لعرض الأزرار.
     */
    public function nextAllowedStatuses(): array
    {
        $next = [];

        if (isset(self::FORWARD_FLOW[$this->status])) {
            $next[] = self::FORWARD_FLOW[$this->status];
        }

        if (in_array($this->status, self::CANCELLABLE_FROM, true)) {
            $next[] = self::STATUS_CANCELLED;
        }

        return $next;
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [self::STATUS_SERVED, self::STATUS_CANCELLED], true);
    }

    /**
     * فحص اتساق actor/source — يعكس CHECK constraint (chk_orders_actor) فالـ migration.
     * مفيد فالـ validation قبل ما توصل للـ database.
     */
    public function hasConsistentActor(): bool
    {
        if ($this->source === self::SOURCE_QR) {
            return $this->guest_access_id !== null && $this->created_by_user_id === null;
        }

        if ($this->source === self::SOURCE_STAFF) {
            return $this->created_by_user_id !== null && $this->guest_access_id === null;
        }

        return false;
    }
}
