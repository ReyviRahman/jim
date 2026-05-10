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
        Schema::create('pt_schedule_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pt_schedule_id')->constrained('pt_schedules')->onDelete('cascade');
            $table->enum('day', ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu']);
            $table->time('time');
            $table->timestamps();

            $table->unique(['pt_schedule_id', 'day']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pt_schedule_days');
    }
};
