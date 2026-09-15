<?php

namespace App\Models;

use App\Support\Concerns\BelongsToCafe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrCode extends Model
{
    use BelongsToCafe;

    protected $fillable = [
        'cafe_id',
        'table_id',
        'token',
        'is_active',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'revoked_at' => 'datetime',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(CafeTable::class, 'table_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
