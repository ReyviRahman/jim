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
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','pt','member','kasir_gym','sales','kasir_minum','head_coach','operasional','cleaning_service') NOT NULL DEFAULT 'member'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','pt','member','kasir_gym','sales','kasir_minum','head_coach','operasional') NOT NULL DEFAULT 'member'");
    }
};
