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
            UPDATE membership_transactions mt
            JOIN users u ON u.id = mt.admin_id
            SET mt.shift = u.shift
            WHERE mt.shift IS NULL
        ');

        DB::statement('
            UPDATE expenses e
            JOIN users u ON u.id = e.admin_id
            SET e.shift = u.shift
            WHERE e.shift IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('membership_transactions')->update(['shift' => null]);
        DB::table('expenses')->update(['shift' => null]);
    }
};
