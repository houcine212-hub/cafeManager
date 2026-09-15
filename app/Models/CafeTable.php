<?php

namespace App\Models;

use App\Support\Concerns\BelongsToCafe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CafeTable extends Model
{
    use BelongsToCafe;

    protected $fillable = [
        'cafe_id',
        'label',
        'capacity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'capacity' => 'integer',
        ];
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class, 'table_id');
    }

    /**
     * رمز الـ QR النشط حالياً للطاولة
     */
    public function activeQrCode(): HasOne
    {
        return $this->hasOne(QrCode::class, 'table_id')->where('is_active', true);
    }
}
