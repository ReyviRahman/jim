<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table): void {
            $table->string('gym_package_name_snapshot')->nullable();
            $table->string('pt_package_name_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table): void {
            $table->dropColumn(['gym_package_name_snapshot', 'pt_package_name_snapshot']);
        });
    }
};
