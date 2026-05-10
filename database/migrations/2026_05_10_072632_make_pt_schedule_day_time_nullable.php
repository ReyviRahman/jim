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
        Schema::table('pt_schedules', function (Blueprint $table) {
            $table->string('day')->nullable()->change();
            $table->time('time')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pt_schedules', function (Blueprint $table) {
            $table->string('day')->nullable(false)->change();
            $table->time('time')->nullable(false)->change();
        });
    }
};
