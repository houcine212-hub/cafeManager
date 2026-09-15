<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_id')->constrained('cafes')->restrictOnDelete();
            $table->foreignId('table_session_id')->constrained('table_sessions')->restrictOnDelete();
            $table->char('token_hash', 64)->unique('uq_ga_token_hash');
            $table->enum('status', ['pending', 'approved', 'revoked', 'expired'])->default('pending');
            $table->timestamp('requested_at')->useCurrent();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // قيد D09: وصول نشط واحد فقط لكل جلسة نشطة
        DB::statement("
            ALTER TABLE guest_accesses
            ADD COLUMN active_session_marker BIGINT UNSIGNED
            GENERATED ALWAYS AS (
                CASE WHEN status IN ('pending','approved') THEN table_session_id ELSE NULL END
            ) STORED,
            ADD UNIQUE INDEX uq_one_active_access_per_session (active_session_marker)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_accesses');
    }
};
