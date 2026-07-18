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
            $table->char('event_hash', 64)->nullable()->unique()->after('accessed_at');
            $table->index('employee_no');
            $table->index('accessed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_events', function (Blueprint $table) {
            $table->dropIndex(['employee_no']);
            $table->dropIndex(['accessed_at']);
            $table->dropUnique(['event_hash']);
            $table->dropColumn('event_hash');
        });
    }
};
