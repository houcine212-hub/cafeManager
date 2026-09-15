<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_id')->constrained('cafes')->restrictOnDelete();
            $table->foreignId('table_session_id')->constrained('table_sessions')->restrictOnDelete();
            $table->enum('source', ['qr', 'staff']);
            $table->foreignId('guest_access_id')->nullable()->constrained('guest_accesses')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->enum('status', ['new', 'accepted', 'preparing', 'ready', 'served', 'cancelled'])->default('new');
            $table->char('idempotency_key', 64);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Idempotency فريد داخل نفس المقهى
            $table->unique(['cafe_id', 'idempotency_key'], 'uq_orders_idempotency');

            // Indexes للأداء السريع فـ Dashboard
            $table->index(['cafe_id', 'table_session_id', 'status'], 'idx_orders_session_status');
            $table->index(['cafe_id', 'created_at'], 'idx_orders_created_at');
        });

        // قيد: الفاعل إما زبون عبر QR أو موظف، وليس كلاهما معاً
        DB::statement("
            ALTER TABLE orders
            ADD CONSTRAINT chk_orders_actor CHECK (
                (source = 'qr' AND guest_access_id IS NOT NULL AND created_by_user_id IS NULL) OR
                (source = 'staff' AND created_by_user_id IS NOT NULL AND guest_access_id IS NULL)
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
