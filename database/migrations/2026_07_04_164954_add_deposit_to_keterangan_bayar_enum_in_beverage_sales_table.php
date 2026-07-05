<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE beverage_sales MODIFY COLUMN keterangan_bayar ENUM('cash', 'tf_bca_qris', 'deposit_hutang_cash', 'deposit_hutang_qris', 'pengeluaran_umum', 'hutang', 'operasional', 'deposit') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE beverage_sales MODIFY COLUMN keterangan_bayar ENUM('cash', 'tf_bca_qris', 'deposit_hutang_cash', 'deposit_hutang_qris', 'pengeluaran_umum', 'hutang', 'operasional') NOT NULL");
    }
};
