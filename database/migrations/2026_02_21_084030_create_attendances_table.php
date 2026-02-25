<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            
            // Relasi ke membership
            $table->foreignId('membership_id')->constrained('memberships')->cascadeOnDelete();
            
            // Relasi ke user yang datang (penting untuk paket couple/group)
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            // Tipe absensi untuk membedakan kedatangan nge-gym biasa atau sesi PT/Visit
            $table->enum('type', ['gym', 'pt', 'visit'])->default('gym');
            
            // Waktu check-in
            $table->timestamp('check_in_time')->useCurrent();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};