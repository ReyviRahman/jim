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
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pt_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('gym_package_id')->constrained('gym_packages');
            // --- SNAPSHOT HARGA & TRANSAKSI ---
            $table->decimal('base_price', 12, 0); 
            $table->decimal('discount_percentage', 5, 2)->default(0); // Persen diskon saat transaksi
            $table->decimal('discount_applied', 12, 0)->default(0); // Hasil hitungan nominal (Rp) dari persen tersebut
            $table->decimal('price_paid', 12, 0); // Total akhir
            $table->integer('total_sessions')->nullable();
            $table->integer('remaining_sessions')->nullable();
            $table->string('member_goal');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['pending', 'active', 'rejected', 'completed'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
