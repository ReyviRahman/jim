<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pt_bookings', function (Blueprint $table) {
            $table->enum('type', ['fleksibel', 'keep'])->default('fleksibel')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('pt_bookings', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
