<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pt_bookings')->where('status', 'completed')->delete();

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE pt_bookings MODIFY COLUMN status ENUM('approved', 'cancelled') NOT NULL DEFAULT 'approved'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE pt_bookings MODIFY COLUMN status ENUM('approved', 'cancelled', 'completed') NOT NULL DEFAULT 'approved'");
        }
    }
};
