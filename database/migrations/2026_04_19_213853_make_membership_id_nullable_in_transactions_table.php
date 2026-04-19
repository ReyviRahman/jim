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
        Schema::table('membership_transactions', function (Blueprint $table) {
            // Mengubah kolom membership_id menjadi boleh kosong (nullable)
            $table->unsignedBigInteger('membership_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('membership_transactions', function (Blueprint $table) {
            // Mengembalikan ke aturan semula (tidak boleh kosong) jika di-rollback
            $table->unsignedBigInteger('membership_id')->nullable(false)->change();
        });
    }
};