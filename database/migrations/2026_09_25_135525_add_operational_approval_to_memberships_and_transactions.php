<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('memberships_and_transactions', function (Blueprint $table) {
            Schema::table('memberships', function (Blueprint $table) {
                $table->foreignId('operational_request_id')->nullable()->unique()->constrained('membership_operational_requests', indexName: 'memberships_operational_request_fk');
            });
            Schema::table('membership_transactions', function (Blueprint $table) {
                $table->foreignId('operational_request_id')->nullable()->constrained('membership_operational_requests', indexName: 'transactions_operational_request_fk');
                $table->enum('payment_method', ['cash', 'transfer', 'qris', 'debit', 'operasional'])->default('cash')->change();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memberships_and_transactions', function (Blueprint $table) {
            if (DB::table('membership_transactions')->where('payment_method', 'operasional')->exists()) {
                throw new RuntimeException('Transaksi Operasional harus ditangani sebelum rollback.');
            }
            Schema::table('membership_transactions', function (Blueprint $table) {
                $table->dropForeign('transactions_operational_request_fk');
                $table->dropColumn('operational_request_id');
                $table->enum('payment_method', ['cash', 'transfer', 'qris', 'debit'])->default('cash')->change();
            });
            Schema::table('memberships', function (Blueprint $table) {
                $table->dropForeign('memberships_operational_request_fk');
                $table->dropColumn('operational_request_id');
            });
        });
    }
};
