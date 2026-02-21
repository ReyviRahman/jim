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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // membership_id dibuat nullable. Jika null, berarti dia "visit" biasa.
            $table->foreignId('membership_id')->nullable()->constrained()->nullOnDelete();
            
            // Kolom untuk mempertegas jenis absensi
            $table->enum('type', ['membership', 'visit'])->default('visit');
            
            // Waktu kedatangan saja
            $table->timestamp('check_in_time')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
