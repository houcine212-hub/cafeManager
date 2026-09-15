<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_id')->constrained('cafes')->restrictOnDelete();
            $table->string('category', 100);
            $table->string('label', 150)->nullable();
            $table->decimal('amount', 10, 2);
            $table->text('description')->nullable();
            $table->date('date');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        // قيد: مبلغ المصروف إيجابي
        DB::statement("
            ALTER TABLE expenses
            ADD CONSTRAINT chk_exp_amount_positive CHECK (amount > 0)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
