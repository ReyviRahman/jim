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
        Schema::table('beverage_restocks', function (Blueprint $table) {
            $table->enum('tipe', ['init', 'restock'])->default('restock')->after('keterangan');
        });
    }

    public function down(): void
    {
        Schema::table('beverage_restocks', function (Blueprint $table) {
            $table->dropColumn('tipe');
        });
    }
};
