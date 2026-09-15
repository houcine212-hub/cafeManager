<?php

namespace App\Models;

use App\Support\Concerns\BelongsToCafe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use BelongsToCafe;

    public const METHOD_CASH = 'cash';
    public const METHOD_CARD = 'card';
    public const METHOD_OTHER = 'other';

    protected $fillable = [
        'cafe_id',
        'table_session_id',
        'amount',
        'method',
        'received_by_user_id',
        'reference',
        'idempotency_key',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * تسوية واحدة صالحة لكل زيارة فـ MVP (D04، uq_one_payment_per_session).
     */
    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    /**
     * Refund = المال رجع فعلاً للزبون. راجع القسم 09 ديال الكادراج
     * للفرق بين refund وcorrection.
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * PaymentCorrection = تصحيح إدخال خاطئ، بلا ادعاء تدفق مالي فعلي.
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(PaymentCorrection::class);
    }

    /**
     * المبلغ الصافي القابل للاسترجاع = amount - refunds الصحيحة.
     * ما كيدمجش corrections (هادوك تصحيح إدخال ماشي صرف فعلي).
     */
    public function refundableAmount(): string
    {
        $refunded = $this->refunds()->sum('amount');

        return bcsub((string) $this->amount, (string) $refunded, 2);
    }
}
