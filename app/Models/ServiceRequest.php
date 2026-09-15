<?php

namespace App\Models;

use App\Support\Concerns\BelongsToCafe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequest extends Model
{
    use BelongsToCafe;

    public const TYPE_WAITER = 'waiter';
    public const TYPE_BILL = 'bill';

    public const STATUS_OPEN = 'open';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'cafe_id',
        'table_session_id',
        'guest_access_id',
        'type',
        'status',
        'handled_by',
    ];

    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    public function guestAccess(): BelongsTo
    {
        return $this->belongsTo(GuestAccess::class);
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * الطلب مفتوح بعد. الضغط المتكرر من نفس النوع خاصو يرجع هاد
     * السجل الموجود ماشي يخلق واحد جديد (uq_sr_open_per_type).
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
