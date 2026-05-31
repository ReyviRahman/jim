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
        Schema::create('pt_payment_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pt_payment_batch_id')->constrained('pt_payment_batches')->cascadeOnDelete();
            $table->foreignId('pt_booking_id')->constrained('pt_bookings')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pt_payment_batch_items');
    }
};
