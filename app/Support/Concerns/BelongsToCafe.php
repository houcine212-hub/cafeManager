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
        // 1. عزل القراءة (Scope Read): مصدر وحيد للحقيقة أو Exception
        // =========================================================================
        static::addGlobalScope('cafe', function (Builder $builder) {
            // A. الحالة العادية: الـ Middleware حدد المقهى بنجاح فـ Container
            if (app()->bound('current_cafe_id')) {
                $builder->where($builder->getModel()->getTable() . '.cafe_id', app('current_cafe_id'));
                return;
            }

            // B. استثناء صريح ومحدود (migrate/db:seed فقط)، ماشي كل Console
            if (static::tenantScopeBypassed()) {
                return;
            }

            // C. FAIL-CLOSED: أي نداء بلا Tenant محدد -> BLOW UP!
            throw new RuntimeException(sprintf(
                'Tenant leak prevented: Attempted to query [%s] without an active tenant context (current_cafe_id is unbound).',
                static::class
            ));
        });

        // =========================================================================
        // 2. تأمين الإدخال (Scope Write): لا حفظ بدون cafe_id صحيح
        // =========================================================================
        static::creating(function ($model) {
            // إلا كان المقهى محدد فـ Container (الحالة العادية HTTP/Job)
            if (app()->bound('current_cafe_id')) {
                $expected = (int) app('current_cafe_id');

                // إلا تم تمرير cafe_id صراحة وما كيطابقش السياق النشط -> رفض فوري
                if (! empty($model->cafe_id) && (int) $model->cafe_id !== $expected) {
                    throw new RuntimeException(sprintf(
                        'Tenant mismatch on create [%s]: model cafe_id (%s) does not match active tenant context (%s).',
                        static::class,
                        $model->cafe_id,
                        $expected
                    ));
                }

                // عمرو تلقائياً إذا ما كانش محدد
                $model->cafe_id = $model->cafe_id ?: $expected;
                return;
            }

            // بلا Container context: مسموح غير فـ استثناء صريح ومحدود (Seeders)
            if (static::tenantScopeBypassed()) {
                // فهاد الحالة خاص cafe_id يتعطى صراحة، ما كنعمروهش تلقائياً
                if (empty($model->cafe_id)) {
                    throw new RuntimeException(sprintf(
                        'Tenant leak prevented: Attempted to create [%s] without a cafe_id (bypass context requires explicit cafe_id).',
                        static::class
                    ));
                }
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
     * الاستثناء الوحيد المسموح بيه لتجاوز عزل الـ tenant.
     *
     * مهم: ماشي كل Console context آمن. queue:work مثلاً كيدير Jobs
     * مرتبطة بمقاهي حقيقية، وخاصها تحدد current_cafe_id بنفسها
     * (فـ handle() ديال الـ Job) بدل ما تتفارغ من هنا بشكل عام.
     *
     * فبيئة testing: ما كاين حتى bypass. كل test خاصو يحدد
     * current_cafe_id بشكل صريح، باش أي bug ديال عزل يبان فالتست.
     */
    protected static function tenantScopeBypassed(): bool
    {
        if (app()->environment('testing')) {
            return false;
        }

        if (! app()->runningInConsole()) {
            return false;
        }

        $allowedCommands = config('tenancy.console_bypass_commands', [
            'migrate',
            'migrate:fresh',
            'migrate:rollback',
            'migrate:refresh',
            'migrate:reset',
            'db:seed',
            'db:wipe',
        ]);

        return in_array(static::currentArtisanCommand(), $allowedCommands, true);
    }

    /**
     * اسم أمر Artisan الجاري تنفيذه دابا (migrate، db:seed...).
     * ما كيشملش queue:work بالتصميم — الهدف بالضبط أنه ما يدخلش
     * فـ allowlist ديال tenantScopeBypassed().
     */
    protected static function currentArtisanCommand(): ?string
    {
        return $_SERVER['argv'][1] ?? null;
    }

    /**
     * علاقة الموديل مع المقهى التابع له
     */
    public function cafe(): BelongsTo
    {
        return $this->belongsTo(Cafe::class);
    }
}
