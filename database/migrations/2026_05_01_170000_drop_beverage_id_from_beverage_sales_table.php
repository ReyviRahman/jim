<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beverage_sales', function (Blueprint $table) {
            $table->dropForeign(['beverage_id']);
            $table->dropColumn('beverage_id');
        });
    }

    public function down(): void
    {
        Schema::table('beverage_sales', function (Blueprint $table) {
            $table->foreignId('beverage_id')->nullable()->constrained('beverages')->onDelete('restrict');
        });
    }
};