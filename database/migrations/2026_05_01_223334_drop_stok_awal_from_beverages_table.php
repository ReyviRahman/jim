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
        Schema::table('beverages', function (Blueprint $table) {
            $table->dropColumn('stok_awal');
        });
    }

    public function down(): void
    {
        Schema::table('beverages', function (Blueprint $table) {
            $table->integer('stok_awal')->default(0)->after('nama_produk');
        });
    }
};
