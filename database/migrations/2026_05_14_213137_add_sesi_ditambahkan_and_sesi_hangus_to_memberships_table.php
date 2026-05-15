<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->integer('sesi_ditambahkan')->nullable()->default(0)->after('remaining_sessions');
            $table->integer('sesi_hangus')->nullable()->default(0)->after('sesi_ditambahkan');
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn(['sesi_ditambahkan', 'sesi_hangus']);
        });
    }
};
