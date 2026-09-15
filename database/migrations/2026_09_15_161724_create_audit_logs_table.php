<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_id')->constrained('cafes')->restrictOnDelete();
            $table->enum('actor_type', ['user', 'guest', 'system']);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 100);
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id');
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->text('reason')->nullable();
            $table->char('correlation_id', 36)->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            // Indexes للأداء السريع فالـ Audit
            $table->index(['cafe_id', 'entity_type', 'entity_id'], 'idx_audit_entity');
            $table->index(['cafe_id', 'occurred_at'], 'idx_audit_occurred');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
