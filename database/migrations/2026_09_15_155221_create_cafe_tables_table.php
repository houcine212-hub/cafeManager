<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cafe_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_id')->constrained('cafes')->restrictOnDelete();
            $table->string('label', 50); // مثال: "Table 6"
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // اسم الطاولة فريد داخل نفس المقهى
            $table->unique(['cafe_id', 'label'], 'uq_table_label_per_cafe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cafe_tables');
    }
};
