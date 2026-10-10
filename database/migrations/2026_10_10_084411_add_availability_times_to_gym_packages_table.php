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
            $table->time('available_from')->nullable();
            $table->time('available_until')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gym_packages', function (Blueprint $table) {
            $table->dropColumn(['available_from', 'available_until']);
        });
    }
};
