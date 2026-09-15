<?php

namespace App\Models;

use App\Support\Concerns\BelongsToCafe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use BelongsToCafe;

    protected $fillable = [
        'cafe_id',
        'category_id',
        'name',
        'description',
        'price',
        'image',
        'is_available',
        'is_active',
        'track_stock',
        'stock_quantity',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_available' => 'boolean',
            'is_active' => 'boolean',
            'track_stock' => 'boolean',
            'stock_quantity' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * منطق القرار D15: فحص هل المنتوج متاح للطلب
     */
    public function isAvailableForOrder(): bool
    {
        if (! $this->is_active || ! $this->is_available) {
            return false;
        }

        // إذا كان متتبع بالستوك، خاص الكمية تكون كبر من 0
        if ($this->track_stock) {
            return $this->stock_quantity > 0;
        }

        return true;
    }

    /**
     * Scope لجلب المنتجات المتاحة فقط للـ Menu
     */
    public function scopeAvailableForOrder(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('is_available', true)
            ->where(function (Builder $q) {
                $q->where('track_stock', false)
                  ->orWhere(function (Builder $sub) {
                      $sub->where('track_stock', true)
                          ->where('stock_quantity', '>', 0);
                  });
            });
    }
}
