<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_id')->constrained('cafes')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('image')->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('track_stock')->default(false); // D15
            $table->integer('stock_quantity')->nullable();   // D15 Cache
            $table->timestamps();

            // Indexes للأداء السريع فـ Polling
            $table->index(['cafe_id', 'category_id', 'is_active'], 'idx_products_cafe_category');
            $table->index(['cafe_id', 'track_stock', 'stock_quantity'], 'idx_products_tracked');
        });

        // القيود الصارمة ديال الأثمنة والستوك D15
        DB::statement("
            ALTER TABLE products
            ADD CONSTRAINT chk_products_price_nonneg CHECK (price >= 0),
            ADD CONSTRAINT chk_products_stock_nonneg CHECK (stock_quantity IS NULL OR stock_quantity >= 0),
            ADD CONSTRAINT chk_products_stock_consistency CHECK (
                (track_stock = 0 AND stock_quantity IS NULL) OR
                (track_stock = 1 AND stock_quantity IS NOT NULL)
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
