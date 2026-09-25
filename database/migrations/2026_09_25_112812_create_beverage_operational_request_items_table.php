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
        Schema::create('beverage_operational_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('beverage_operational_requests');
            $table->foreignId('beverage_id')->constrained('beverages');
            $table->string('nama_produk');
            $table->unsignedInteger('jumlah_beli');
            $table->unsignedInteger('harga_satuan');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beverage_operational_request_items');
    }
};
