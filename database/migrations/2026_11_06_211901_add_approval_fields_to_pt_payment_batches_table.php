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
            $table->unsignedBigInteger('paid_by')->nullable()->change();
            $table->string('status')->default('approved')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pt_payment_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_by');
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'approved_at']);
        });
    }
};
