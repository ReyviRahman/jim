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
        Schema::create('membership_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->uuid('submission_token')->unique();
            $table->unsignedInteger('months');
            $table->decimal('monthly_price', 12, 0);
            $table->decimal('total_amount', 12, 0);
            $table->date('previous_end_date');
            $table->date('new_end_date');
            $table->timestamps();
        });

        Schema::table('membership_transactions', function (Blueprint $table) {
            $table->foreignId('membership_hold_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('membership_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('membership_hold_id');
        });
        Schema::dropIfExists('membership_holds');
    }
};
