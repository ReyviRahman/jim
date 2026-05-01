<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beverage_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('no_faktur')->unique();
            $table->date('tanggal_order');
            $table->date('tanggal_menerima')->nullable();
            $table->string('diterima_oleh')->nullable();
            $table->string('supplier_name');
            $table->enum('status', ['pending', 'lunas'])->default('pending');
            $table->enum('metode_pembayaran', ['cash', 'tf_bca', 'qris', 'hutang'])->default('cash');
            $table->timestamps();
        });

        Schema::create('beverage_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beverage_invoice_id')->constrained('beverage_invoices')->onDelete('cascade');
            $table->foreignId('beverage_id')->nullable()->constrained('beverages')->onDelete('set null');
            $table->string('nama_barang');
            $table->integer('qty');
            $table->integer('harga_perdus');
            $table->integer('biaya_ppn');
            $table->integer('total');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beverage_invoice_items');
        Schema::dropIfExists('beverage_invoices');
    }
};
