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
            $table->decimal('potongan', 15, 2)->nullable()->default(0)->after('paid_by');
            $table->text('keterangan_potongan')->nullable()->after('potongan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pt_payment_batches', function (Blueprint $table) {
            $table->dropColumn(['potongan', 'keterangan_potongan']);
        });
    }
};
