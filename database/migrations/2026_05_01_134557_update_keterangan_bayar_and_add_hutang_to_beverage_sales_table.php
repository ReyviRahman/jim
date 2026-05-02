<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. PERLUAS ENUM: Tambahkan opsi baru ke ENUM TANPA menghapus 'deposit_hutang' terlebih dahulu
        DB::statement("ALTER TABLE beverage_sales MODIFY COLUMN keterangan_bayar ENUM('cash', 'deposit_hutang', 'tf_bca_qris', 'deposit_hutang_cash', 'deposit_hutang_qris', 'pengeluaran_umum', 'hutang', 'operasional') NOT NULL");

        // 2. UPDATE DATA: Sekarang aman untuk mengubah data karena 'deposit_hutang_cash' sudah valid di ENUM
        DB::table('beverage_sales')
            ->where('keterangan_bayar', 'deposit_hutang')
            ->update(['keterangan_bayar' => 'deposit_hutang_cash']);

        // 3. BERSIHKAN ENUM: Hapus opsi 'deposit_hutang' yang lama secara permanen
        DB::statement("ALTER TABLE beverage_sales MODIFY COLUMN keterangan_bayar ENUM('cash', 'tf_bca_qris', 'deposit_hutang_cash', 'deposit_hutang_qris', 'pengeluaran_umum', 'hutang', 'operasional') NOT NULL");

        // 4. Tambahkan kolom pencatat hutang
        Schema::table('beverage_sales', function (Blueprint $table) {
            $table->string('nama_penghutang')->nullable()->after('keterangan_bayar')->comment('Diisi jika keterangan_bayar adalah hutang');
            $table->boolean('is_lunas')->default(false)->after('nama_penghutang')->comment('Tanda false=belum lunas, true=sudah lunas');
        });
    }

    public function down(): void
    {
        // 1. Hapus kolom pencatat hutang
        Schema::table('beverage_sales', function (Blueprint $table) {
            $table->dropColumn(['nama_penghutang', 'is_lunas']);
        });

        // 2. PERLUAS ENUM UNTUK ROLLBACK: Masukkan kembali 'deposit_hutang'
        DB::statement("ALTER TABLE beverage_sales MODIFY COLUMN keterangan_bayar ENUM('cash', 'deposit_hutang', 'tf_bca_qris', 'deposit_hutang_cash', 'deposit_hutang_qris', 'pengeluaran_umum', 'hutang', 'operasional') NOT NULL");

        // 3. KEMBALIKAN DATA: Ubah kembali ke 'deposit_hutang'
        DB::table('beverage_sales')
            ->whereIn('keterangan_bayar', ['deposit_hutang_cash', 'deposit_hutang_qris'])
            ->update(['keterangan_bayar' => 'deposit_hutang']);

        // 4. BERSIHKAN ENUM: Hapus opsi yang baru agar kembali ke keadaan persis sebelum migrasi
        DB::statement("ALTER TABLE beverage_sales MODIFY COLUMN keterangan_bayar ENUM('cash', 'deposit_hutang', 'tf_bca_qris', 'pengeluaran_umum', 'hutang', 'operasional') NOT NULL");
    }
};
