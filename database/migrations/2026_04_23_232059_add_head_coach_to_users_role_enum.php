<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        \DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','pt','member','kasir_gym','sales','kasir_minum','head_coach') NOT NULL DEFAULT 'member'");
    }

    public function down(): void
    {
        \DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','pt','member','kasir_gym','sales','kasir_minum') NOT NULL DEFAULT 'member'");
    }
};
