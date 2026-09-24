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
            $table->string('studio_type', 20)->nullable();
            $table->index(['studio_type', 'status', 'booking_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pt_bookings', function (Blueprint $table) {
            $table->dropIndex(['studio_type', 'status', 'booking_date']);
            $table->dropColumn('studio_type');
        });
    }
};
