<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beverage_restocks', function (Blueprint $table) {
            $table->id();
            // Ubah cascade jadi restrict
            $table->foreignId('beverage_id')->constrained('beverages')->onDelete('restrict');
            $table->date('tanggal');
            $table->integer('jumlah_tambah');
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beverage_restocks');
    }
};
