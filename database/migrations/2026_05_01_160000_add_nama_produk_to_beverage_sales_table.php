<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beverage_sales', function (Blueprint $table) {
            $table->string('nama_produk')->after('beverage_id')->comment(' Menyimpan nama produk langsung, bukan beverage_id');
        });
    }

    public function down(): void
    {
        Schema::table('beverage_sales', function (Blueprint $table) {
            $table->dropColumn('nama_produk');
        });
    }
};
