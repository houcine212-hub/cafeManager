<?php

namespace App\Models;

use App\Support\Concerns\BelongsToCafe;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use BelongsToCafe;

    // الجدول عندو occurred_at غير، بلا created_at/updated_at
    public $timestamps = false;

    public const ACTOR_USER = 'user';
    public const ACTOR_GUEST = 'guest';
    public const ACTOR_SYSTEM = 'system';

    protected $fillable = [
        'cafe_id',
        'actor_type',
        'actor_id',
        'action',
        'entity_type',
        'entity_id',
        'before_state',
        'after_state',
        'reason',
        'correlation_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'before_state' => 'array',
            'after_state' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * ملاحظة: actor_id بلا foreign key فالـ migration (عمداً — actor_type
     * يقدر يكون 'user' أو 'guest' أو 'system'، وما كاينش جدول واحد يجمعهم).
     * ما كنديروش belongsTo/morphTo هنا باش ما نفترضوش جدول غلط.
     * الربط بـ users كيتدار يدوياً فـ query لو actor_type === 'user'.
     */

    /**
     * append-only — القسم 13 ديال الكادراج. ما خاصش يتبدل أو يتحذف
     * من بعد ما يتسجل.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('AuditLog is append-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new \RuntimeException('AuditLog is append-only and cannot be deleted.');
        });
    }
}
