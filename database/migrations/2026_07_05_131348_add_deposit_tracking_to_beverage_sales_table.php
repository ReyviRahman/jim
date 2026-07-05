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
        Schema::table('beverage_sales', function (Blueprint $table) {
            $table->foreignId('deposit_beverage_id')->nullable()->after('beverage_id')->constrained('deposit_beverages')->onDelete('restrict');
            $table->integer('deposit_amount')->nullable()->after('deposit_beverage_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beverage_sales', function (Blueprint $table) {
            $table->dropForeign(['deposit_beverage_id']);
            $table->dropColumn(['deposit_beverage_id', 'deposit_amount']);
        });
    }
};
