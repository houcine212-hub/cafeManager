<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_id')->constrained('cafes')->restrictOnDelete();
            $table->foreignId('table_id')->constrained('cafe_tables')->restrictOnDelete();
            $table->char('token', 64)->unique('uq_qr_token');
            $table->boolean('is_active')->default(true);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['table_id', 'is_active'], 'idx_qr_table');
        });

        // قيد رمز نشط واحد فقط للطاولة
        DB::statement("
            ALTER TABLE qr_codes
            ADD COLUMN active_table_id BIGINT UNSIGNED
            GENERATED ALWAYS AS (CASE WHEN is_active = 1 THEN table_id ELSE NULL END) STORED,
            ADD UNIQUE INDEX uq_qr_active_per_table (active_table_id)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
    }
};
