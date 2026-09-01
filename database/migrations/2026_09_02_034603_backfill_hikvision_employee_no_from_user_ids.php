<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE users
            SET hikvision_employee_no = CONCAT('00', id)
            WHERE hikvision_employee_no IS NULL OR hikvision_employee_no = ''
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This backfill cannot be reversed without risking removal of pre-existing mappings.
    }
};
