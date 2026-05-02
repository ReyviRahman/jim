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
        // Drop foreign key constraint first (MySQL requires this before dropping unique index)
        Schema::table('beverage_stok_snapshots', function (Blueprint $table) {
            $table->dropForeign(['beverage_id']);
        });

        // Drop unique index
        Schema::table('beverage_stok_snapshots', function (Blueprint $table) {
            $table->dropUnique(['beverage_id', 'tanggal']);
        });

        // Rename column, add tipe, add new unique index, re-add foreign key
        Schema::table('beverage_stok_snapshots', function (Blueprint $table) {
            $table->renameColumn('stok_akhir', 'jumlah');
            $table->enum('tipe', ['init', 'last'])->default('last')->after('tanggal');
            $table->unique(['beverage_id', 'tanggal', 'tipe']);
            $table->foreign('beverage_id')->references('id')->on('beverages')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key constraint first
        Schema::table('beverage_stok_snapshots', function (Blueprint $table) {
            $table->dropForeign(['beverage_id']);
        });

        // Drop new unique index and tipe column
        Schema::table('beverage_stok_snapshots', function (Blueprint $table) {
            $table->dropUnique(['beverage_id', 'tanggal', 'tipe']);
            $table->dropColumn('tipe');
            $table->renameColumn('jumlah', 'stok_akhir');
            $table->unique(['beverage_id', 'tanggal']);
            $table->foreign('beverage_id')->references('id')->on('beverages')->onDelete('cascade');
        });
    }
};
