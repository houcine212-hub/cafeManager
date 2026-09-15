<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_id')->constrained('cafes')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->integer('delta');
            $table->integer('quantity_after');
            $table->enum('reason', ['sale', 'restock', 'waste', 'correction', 'initial']);
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->restrictOnDelete();
            $table->enum('actor_type', ['user', 'system']);
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            // سطر طلب واحد ما كيخلقش جوج حركات بيع
            $table->unique(['order_item_id', 'reason'], 'uq_sm_sale_per_item');

            $table->index(['cafe_id', 'product_id', 'occurred_at'], 'idx_sm_product_time');
        });

        // قيود شكل حركة الستوك (D15)
        DB::statement("
            ALTER TABLE stock_movements
            ADD CONSTRAINT chk_sm_delta_nonzero CHECK (delta <> 0),
            ADD CONSTRAINT chk_sm_qty_after_nonneg CHECK (quantity_after >= 0),
            ADD CONSTRAINT chk_sm_reason_shape CHECK (
                (reason = 'sale' AND delta < 0 AND order_item_id IS NOT NULL AND actor_type = 'system') OR
                (reason = 'restock' AND delta > 0 AND order_item_id IS NULL AND actor_type = 'user') OR
                (reason = 'waste' AND delta < 0 AND actor_type = 'user') OR
                (reason IN ('correction','initial') AND actor_type = 'user')
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
