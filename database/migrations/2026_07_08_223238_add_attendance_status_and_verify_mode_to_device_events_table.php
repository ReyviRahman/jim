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
        Schema::table('device_events', function (Blueprint $table) {
            $table->string('attendance_status')->nullable()->after('swipe_result');
            $table->string('verify_mode')->nullable()->after('attendance_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_events', function (Blueprint $table) {
            $table->dropColumn(['attendance_status', 'verify_mode']);
        });
    }
};
