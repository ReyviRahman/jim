<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ubah enum dengan Raw DB Statement
        DB::statement("ALTER TABLE beverage_sales MODIFY COLUMN keterangan_bayar ENUM('cash', 'deposit_hutang', 'tf_bca_qris', 'pengeluaran_umum', 'hutang', 'operasional') NOT NULL");
    }

    public function down(): void
    {
        // Kembalikan ke enum sebelumnya jika di-rollback
        DB::statement("ALTER TABLE beverage_sales MODIFY COLUMN keterangan_bayar ENUM('cash', 'qris', 'tf_bca', 'deposit_hutang', 'hutang') NOT NULL");
    }
};