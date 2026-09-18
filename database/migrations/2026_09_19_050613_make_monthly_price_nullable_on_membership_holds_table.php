<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('membership_holds', function (Blueprint $table) {
            $table->decimal('monthly_price', 12, 0)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('membership_holds')->whereNull('monthly_price')->exists()) {
            throw new RuntimeException('Cannot restore monthly pricing while manually priced holds exist.');
        }

        Schema::table('membership_holds', function (Blueprint $table) {
            $table->decimal('monthly_price', 12, 0)->nullable(false)->change();
        });
    }
};
