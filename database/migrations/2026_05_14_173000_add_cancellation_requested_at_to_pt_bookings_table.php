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
        Schema::table('pt_bookings', function (Blueprint $table) {
            $table->timestamp('cancellation_requested_at')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('pt_bookings', function (Blueprint $table) {
            $table->dropColumn('cancellation_requested_at');
        });
    }
};
