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
        Schema::table('gym_packages', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_months')->default(0);
            $table->unsignedSmallInteger('duration_weeks')->default(0);
            $table->unsignedSmallInteger('duration_days')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gym_packages', function (Blueprint $table) {
            $table->dropColumn(['duration_months', 'duration_weeks', 'duration_days']);
        });
    }
};
