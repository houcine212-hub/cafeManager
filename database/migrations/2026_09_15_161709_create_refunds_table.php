<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_id')->constrained('cafes')->restrictOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->foreignId('sale_adjustment_id')->nullable()->constrained('sale_adjustments')->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('method', ['cash', 'card', 'other']);
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->string('reference')->nullable();
            $table->text('reason');
            $table->timestamp('refunded_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();
        });

        // قيد: مبلغ الإرجاع إيجابي
        DB::statement("
            ALTER TABLE refunds
            ADD CONSTRAINT chk_rf_amount_positive CHECK (amount > 0)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
