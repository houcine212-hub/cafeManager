<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_id')->constrained('cafes')->restrictOnDelete();
            $table->foreignId('table_id')->constrained('cafe_tables')->restrictOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at')->useCurrent();
            $table->enum('status', ['open', 'checkout', 'closed'])->default('open');
            $table->timestamp('checkout_at')->nullable();
            $table->foreignId('checkout_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->decimal('total_final', 10, 2)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->enum('closure_reason', ['paid', 'empty', 'exception_unpaid'])->nullable();
            $table->timestamps();

            $table->index(['cafe_id', 'status'], 'idx_ts_status');
        });

        // قيد: زيارة نشطة واحدة فقط لكل طاولة
        DB::statement("
            ALTER TABLE table_sessions
            ADD COLUMN active_table_marker BIGINT UNSIGNED
            GENERATED ALWAYS AS (
                CASE WHEN status IN ('open','checkout') THEN table_id ELSE NULL END
            ) STORED,
            ADD UNIQUE INDEX uq_one_active_session_per_table (active_table_marker)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('table_sessions');
    }
};
