<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pt_bookings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('membership_id')->constrained('memberships')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pt_id')->constrained('users')->cascadeOnDelete();

            $table->date('booking_date');
            $table->time('booking_time');
            $table->enum('status', ['approved', 'cancelled', 'completed'])->default('approved');
            $table->text('notes')->nullable();

            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pt_bookings');
    }
};
