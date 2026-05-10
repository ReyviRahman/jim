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
        Schema::create('pt_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained('memberships')->onDelete('cascade');
            $table->enum('day', ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu']);
            $table->time('time');
            $table->enum('type', ['fleksibel', 'keep'])->default('fleksibel');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pt_schedules');
    }
};
