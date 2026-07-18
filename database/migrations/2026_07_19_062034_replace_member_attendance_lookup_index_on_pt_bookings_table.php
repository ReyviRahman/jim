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
            $table->index(
                ['membership_id', 'booking_date', 'attendance', 'status', 'booking_time'],
                'pt_bookings_membership_attendance_lookup_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pt_bookings', function (Blueprint $table) {
            $table->dropIndex('pt_bookings_membership_attendance_lookup_index');
        });
    }
};
