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
        Schema::table('pt_bookings', function (Blueprint $table) {
            $table->enum('attendance', ['not_yet', 'attended', 'noshow'])->default('not_yet')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('pt_bookings', function (Blueprint $table) {
            $table->dropColumn('attendance');
        });
    }
};
