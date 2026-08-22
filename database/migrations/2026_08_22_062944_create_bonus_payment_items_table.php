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
        Schema::create('bonus_payment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bonus_payment_id')->constrained('bonus_payments')->cascadeOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained('memberships')->nullOnDelete();
            $table->string('member_name');
            $table->string('package_name');
            $table->decimal('nominal', 15, 2);
            $table->decimal('nominal_akhir', 15, 2);
            $table->date('payment_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonus_payment_items');
    }
};
