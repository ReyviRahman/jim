<?php

namespace App\Console\Commands;

use App\Models\Beverage;
use App\Models\BeverageStokSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncBeverageStock extends Command
{
    protected $signature = 'beverages:sync-stock
                            {type : Jenis sync (init|last|all)}
                            {--dry-run : Jalankan tanpa mengupdate database}';

    protected $description = 'Sinkronisasi stok minuman otomatis (init=stok awal, last=stok akhir).';

    public function handle(): int
    {
        $type = $this->argument('type');
        $dryRun = $this->option('dry-run');
        
        // Mengambil waktu saat ini (tanggal & waktu lengkap)
        $now = now();
        $today = $now->toDateString(); // Format: YYYY-MM-DD
        $timestamp = $now->toDateTimeString(); // Format: YYYY-MM-DD HH:MM:SS

        if (! in_array($type, ['init', 'last', 'all'])) {
            $this->error('Tipe harus init, last, atau all');

            return self::FAILURE;
        }

        $types = $type === 'all' ? ['init', 'last'] : [$type];

        // Menambahkan timestamp pada output console dan log
        $this->info("Memulai sinkronisasi stok minuman... (Waktu: {$timestamp}, Tipe: {$type})");
        Log::info("[SyncBeverageStock] Memulai sync {$type} pada {$timestamp}");

        $beverages = Beverage::query()->get();

        if ($beverages->isEmpty()) {
            $this->warn('Tidak ada produk minuman yang ditemukan.');

            return self::SUCCESS;
        }

        $count = 0;

        foreach ($beverages as $beverage) {
            foreach ($types as $t) {
                if (! $dryRun) {
                    BeverageStokSnapshot::updateOrCreate(
                        [
                            'beverage_id' => $beverage->id,
                            'tanggal' => $today,
                            'tipe' => $t,
                        ],
                        [
                            'jumlah' => $beverage->stok_sekarang,
                            // Opsional: Buka komentar di bawah jika kamu punya kolom khusus untuk mencatat waktu eksekusi
                            // 'waktu_eksekusi' => $timestamp, 
                        ]
                    );
                }

                $count++;
                $actionText = $dryRun ? '[DRY-RUN] Would create/update' : 'Created/Updated';
                Log::info("[SyncBeverageStock] {$actionText} snapshot #{$beverage->id} (tipe: {$t}, jumlah: {$beverage->stok_sekarang}, timestamp: {$timestamp})");
            }
        }

        $finishTimestamp = now()->toDateTimeString();

        if ($dryRun) {
            $this->warn("DRY RUN: {$count} record akan disinkronkan. (Selesai: {$finishTimestamp})");
        } else {
            $this->info("{$count} record berhasil disinkronkan pada {$finishTimestamp}.");
        }

        Log::info("[SyncBeverageStock] Selesai pada {$finishTimestamp}. Total: {$count} record.");

        return self::SUCCESS;
    }
}