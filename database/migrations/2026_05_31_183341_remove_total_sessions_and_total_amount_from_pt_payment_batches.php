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
        Schema::table('pt_payment_batches', function (Blueprint $table) {
            $table->dropColumn(['total_sessions', 'total_amount']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pt_payment_batches', function (Blueprint $table) {
            $table->unsignedInteger('total_sessions')->after('date_end');
            $table->unsignedBigInteger('total_amount')->after('total_sessions');
        });
    }
};
