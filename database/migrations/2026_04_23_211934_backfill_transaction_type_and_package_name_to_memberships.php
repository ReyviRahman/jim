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
        DB::statement('
            UPDATE memberships m
            JOIN (
                SELECT t1.membership_id, t1.transaction_type, t1.package_name
                FROM membership_transactions t1
                WHERE t1.id = (
                    SELECT MAX(t2.id)
                    FROM membership_transactions t2
                    WHERE t2.membership_id = t1.membership_id
                )
            ) latest ON m.id = latest.membership_id
            SET m.transaction_type = latest.transaction_type,
                m.package_name = latest.package_name
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Backfill migration is irreversible by design
    }
};
