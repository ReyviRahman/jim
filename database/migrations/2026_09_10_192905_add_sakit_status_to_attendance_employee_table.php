<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance_employee', function (Blueprint $table) {
            $table->enum('status', ['hadir', 'izin', 'off', 'sakit'])->default('hadir')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('attendance_employee')->where('status', 'sakit')->exists()) {
            throw new RuntimeException('Rollback dihentikan: masih ada absensi berstatus Sakit. Sesuaikan data sebelum rollback.');
        }

        Schema::table('attendance_employee', function (Blueprint $table) {
            $table->enum('status', ['hadir', 'izin', 'off'])->default('hadir')->change();
        });
    }
};
