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
            $table->foreignId('parent_beverage_sale_id')->nullable()->after('deposit_amount')->constrained('beverage_sales')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beverage_sales', function (Blueprint $table) {
            $table->dropForeign(['parent_beverage_sale_id']);
            $table->dropColumn('parent_beverage_sale_id');
        });
    }
};
