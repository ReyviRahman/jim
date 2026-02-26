<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // 1. Ubah membership_id agar boleh kosong (null) untuk absen Coach
            $table->unsignedBigInteger('membership_id')->nullable()->change();
            
            // 2. Update kolom enum 'type' dengan tambahan 'coach_attendance'
            $table->enum('type', ['gym', 'pt', 'visit', 'coach_attendance'])->default('gym')->change();
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Kembalikan ke aturan semula jika migrasi di-rollback
            $table->unsignedBigInteger('membership_id')->nullable(false)->change();
            $table->enum('type', ['gym', 'pt', 'visit'])->default('gym')->change();
        });
    }
};