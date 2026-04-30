<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beverages', function (Blueprint $table) {
            $table->id();
            $table->string('nama_produk');
            $table->integer('stok_awal')->default(0);
            $table->integer('harga_modal')->comment('Harga beli / modal');
            $table->integer('harga_jual');
            $table->integer('stok_sekarang')->default(0)->comment('Stok dinamis real-time');
            $table->timestamps();
            $table->softDeletes(); // Tambahan: Agar produk yang dihapus tidak benar-benar hilang dari database
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beverages');
    }
};