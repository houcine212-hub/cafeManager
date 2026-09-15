<?php

namespace App\Models;

use App\Support\Concerns\BelongsToCafe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuestAccess extends Model
{
    use BelongsToCafe;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'cafe_id',
        'table_session_id',
        'token_hash',
        'status',
        'requested_at',
        'approved_by',
        'approved_at',
        'revoked_at',
        'expires_at',
    ];

    // token_hash ما خاصوش يبان فأي إخراج (API/logs) — القسم 12/13 ديال الكادراج
    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'revoked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
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
     * الوصول نشط (pending أو approved) — وصول واحد نشط لكل زيارة (D09).
     */
    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED], true);
    }

    /**
     * الوصول معتمد فعلاً من الموظف — الحالة الوحيدة المسموح بيها
     * لإنشاء order من QR. pending/revoked/expired ممنوعين (CreateOrderAction).
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
