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
        Schema::create('deposit_beverages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beverage_sale_id')->nullable()->constrained('beverage_sales')->onDelete('set null');
            $table->string('nama_pelanggan')->nullable();
            $table->integer('nominal');
            $table->integer('sisa_nominal');
            $table->boolean('is_used')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposit_beverages');
    }
};
