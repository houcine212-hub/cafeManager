<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_id')->constrained('cafes')->restrictOnDelete();
            $table->foreignId('table_session_id')->constrained('table_sessions')->restrictOnDelete();
            $table->foreignId('guest_access_id')->nullable()->constrained('guest_accesses')->restrictOnDelete();
            $table->enum('type', ['waiter', 'bill']);
            $table->enum('status', ['open', 'acknowledged', 'resolved', 'cancelled'])->default('open');
            $table->foreignId('handled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['cafe_id', 'table_session_id', 'status'], 'idx_sr_session_status');
        });

        // قيد: Dedup لمنع فتح أكثر من طلب من نفس النوع لنفس الجلسة
        DB::statement("
            ALTER TABLE service_requests
            ADD COLUMN open_dedup_key VARCHAR(150)
            GENERATED ALWAYS AS (
                CASE WHEN status = 'open' THEN CONCAT(table_session_id, '-', type) ELSE NULL END
            ) STORED,
            ADD UNIQUE INDEX uq_sr_open_per_type (open_dedup_key)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
