<?php

namespace App\Support\Concerns;

use App\Models\Cafe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

trait BelongsToCafe
{
    protected static function bootBelongsToCafe(): void
    {
        // =========================================================================
        // 1. عزل القراءة (Scope Read): مصدر وحيد للحقيقة أو تفرگيع Exception
        // =========================================================================
        static::addGlobalScope('cafe', function (Builder $builder) {
            // A. الحالة العادية: الـ Middleware حدد المقهى بنجاح فـ Container
            if (app()->bound('current_cafe_id')) {
                $builder->where($builder->getModel()->getTable() . '.cafe_id', app('current_cafe_id'));
                return;
            }

            // B. حالة الـ Console / Seeders (بلا اختبارات آلية): مسموح بالقراءة للتهيئة
            if (app()->runningInConsole() && ! app()->runningUnitTests()) {
                return;
            }

            // C. FAIL-CLOSED: أي نداء فـ HTTP Request أو فـ وسط Test بلا Tenant -> BLOW UP!
            throw new RuntimeException(sprintf(
                'Tenant leak prevented: Attempted to query [%s] without an active tenant context (current_cafe_id is unbound).',
                static::class
            ));
        });

        // =========================================================================
        // 2. تأمين الإدخال (Scope Write): لا حفظ بدون cafe_id
        // =========================================================================
        static::creating(function ($model) {
            // إلا كان الـ Model واخد cafe_id صراحة (بحال فـ Seeder / Factory)، دوز
            if (! empty($model->cafe_id)) {
                return;
            }

            // إلا كان المقهى محدد فـ Container، عمرو تلقائياً
            if (app()->bound('current_cafe_id')) {
                $model->cafe_id = app('current_cafe_id');
                return;
            }

            // مسموح فـ Artisan commands فقط
            if (app()->runningInConsole() && ! app()->runningUnitTests()) {
                return;
            }

            // FAIL-CLOSED عند الحفظ
            throw new RuntimeException(sprintf(
                'Tenant leak prevented: Attempted to create [%s] without a cafe_id.',
                static::class
            ));
        });
    }

    /**
     * علاقة الموديل مع المقهى التابع له
     */
    public function cafe(): BelongsTo
    {
        return $this->belongsTo(Cafe::class);
    }
}
