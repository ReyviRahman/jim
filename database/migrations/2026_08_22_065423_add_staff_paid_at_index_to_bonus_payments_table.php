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
        Schema::table('bonus_payments', function (Blueprint $table) {
            $table->index(['staff_user_id', 'paid_at'], 'bonus_payments_staff_paid_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bonus_payments', function (Blueprint $table) {
            $table->dropIndex('bonus_payments_staff_paid_at_index');
        });
    }
};
