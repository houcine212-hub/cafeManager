<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_id')->constrained('cafes')->restrictOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('product_name_snapshot', 100);
            $table->decimal('unit_price_snapshot', 10, 2);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('line_total', 10, 2);
            $table->string('note')->nullable();
            $table->timestamps();
        });

        // قيود الكمية والثمن
        DB::statement("
            ALTER TABLE order_items
            ADD CONSTRAINT chk_oi_qty_positive CHECK (quantity > 0),
            ADD CONSTRAINT chk_oi_price_nonneg CHECK (unit_price_snapshot >= 0)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
