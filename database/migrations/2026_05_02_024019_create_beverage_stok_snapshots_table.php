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
        Schema::create('beverage_stok_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beverage_id')->constrained('beverages')->onDelete('cascade');
            $table->date('tanggal')->index();
            $table->integer('stok_akhir');
            $table->timestamps();

            $table->unique(['beverage_id', 'tanggal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beverage_stok_snapshots');
    }
};
