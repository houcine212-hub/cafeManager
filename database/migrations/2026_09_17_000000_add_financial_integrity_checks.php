<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE sale_adjustments
            ADD CONSTRAINT chk_sale_adjustment_delta_nonzero
            CHECK (amount_delta <> 0)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE payment_corrections
            ADD CONSTRAINT chk_payment_correction_amounts_positive
            CHECK (amount_before > 0 AND amount_after > 0)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE payment_corrections
            ADD CONSTRAINT chk_payment_correction_amount_changed
            CHECK (amount_before <> amount_after)
        SQL);
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE sale_adjustments DROP CHECK chk_sale_adjustment_delta_nonzero'
        );

        DB::statement(
            'ALTER TABLE payment_corrections DROP CHECK chk_payment_correction_amounts_positive'
        );

        DB::statement(
            'ALTER TABLE payment_corrections DROP CHECK chk_payment_correction_amount_changed'
        );
    }
};
