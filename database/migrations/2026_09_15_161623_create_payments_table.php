<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_id')->constrained('cafes')->restrictOnDelete();
            $table->foreignId('table_session_id')->constrained('table_sessions')->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('method', ['cash', 'card', 'other']);
            $table->foreignId('received_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('reference')->nullable();
            $table->char('idempotency_key', 64);
            $table->timestamp('paid_at')->useCurrent();
            $table->timestamps();

            // Idempotency فريد داخل نفس المقهى
            $table->unique(['cafe_id', 'idempotency_key'], 'uq_pay_idempotency');

            // D04: تسوية واحدة صالحة لكل زيارة فالـ MVP
            $table->unique('table_session_id', 'uq_one_payment_per_session');
        });

        // قيد: المبلغ إيجابي
        DB::statement("
            ALTER TABLE payments
            ADD CONSTRAINT chk_pay_amount_positive CHECK (amount > 0)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
