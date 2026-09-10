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
        Schema::create('attendance_employee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_event_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('nama_di_alat')->nullable();
            $table->date('attendance_date')->index();
            $table->timestamp('check_in_time');
            $table->timestamp('check_out_time')->nullable();
            $table->string('shift_code');
            $table->string('shift_name');
            $table->string('shift_role');
            $table->time('shift_start_time');
            $table->time('shift_end_time');
            $table->timestamp('scheduled_start_at');
            $table->timestamp('scheduled_end_at');
            $table->timestamp('checkout_deadline_at');
            $table->timestamps();
            $table->unique(['user_id', 'attendance_date']);
            $table->index(['user_id', 'checkout_deadline_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_employee');
    }
};
