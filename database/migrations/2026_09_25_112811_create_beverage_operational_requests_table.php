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
        Schema::create('beverage_operational_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->constrained('users');
            $table->string('nama_staff');
            $table->string('shift');
            $table->text('reason');
            $table->string('status')->default('pending')->index();
            $table->dateTime('requested_at');
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->dateTime('decided_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('deposit_beverage_id')->nullable()->constrained('deposit_beverages')->nullOnDelete();
            $table->unsignedInteger('deposit_amount')->default(0);
            $table->unsignedInteger('total');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beverage_operational_requests');
    }
};
