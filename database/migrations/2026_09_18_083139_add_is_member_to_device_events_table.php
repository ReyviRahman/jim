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
            $table->boolean('is_member')->nullable()->default(null)->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_events', function (Blueprint $table) {
            $table->dropColumn('is_member');
        });
    }
};
