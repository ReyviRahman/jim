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
            $table->foreignId('operational_request_id')->nullable()->constrained('beverage_operational_requests');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beverage_sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('operational_request_id');
        });
    }
};
