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
            $table->string('employee_no')->nullable()->after('event_type');
            $table->string('name')->nullable()->after('employee_no');
            $table->string('card_no')->nullable()->after('name');
            $table->string('door_no')->nullable()->after('card_no');
            $table->string('swipe_result')->nullable()->after('door_no');
            $table->timestamp('accessed_at')->nullable()->after('swipe_result');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_events', function (Blueprint $table) {
            $table->dropColumn(['employee_no', 'name', 'card_no', 'door_no', 'swipe_result', 'accessed_at']);
        });
    }
};
