<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beverage_sales', function (Blueprint $table) {
            $table->id();
            // Ubah cascade jadi restrict
            $table->foreignId('beverage_id')->constrained('beverages')->onDelete('restrict');
            $table->string('nama_staff');
            $table->dateTime('waktu_transaksi');
            $table->enum('shift', ['pagi', 'siang'])->default('pagi');
            $table->integer('jumlah_beli');
            $table->integer('harga_satuan')->comment('Snapshot harga jual saat transaksi');
            $table->integer('total_harga')->generatedAs('jumlah_beli * harga_satuan');
            $table->enum('keterangan_bayar', [
                'lunas', 
                'cash', 
                'qris', 
                'tf_bca', 
                'deposit_hutang', 
                'belum_bayar'
            ]);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beverage_sales');
    }
};