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
        $today = date('Y-m-d');

        if (! in_array($type, ['init', 'last', 'all'])) {
            $this->error('Tipe harus init, last, atau all');

            return self::FAILURE;
        }

        $types = $type === 'all' ? ['init', 'last'] : [$type];

        $this->info("Memulai sinkronisasi stok minuman... (Tanggal: {$today}, Tipe: {$type})");
        Log::info("[SyncBeverageStock] Memulai sync {$type} untuk tanggal {$today}");

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
                        ]
                    );
                }

                $count++;
                $actionText = $dryRun ? '[DRY-RUN] Would create/update' : 'Created/Updated';
                Log::info("[SyncBeverageStock] {$actionText} snapshot #{$beverage->id} (tipe: {$t}, jumlah: {$beverage->stok_sekarang})");
            }
        }

        if ($dryRun) {
            $this->warn("DRY RUN: {$count} record akan disinkronkan.");
        } else {
            $this->info("{$count} record berhasil disinkronkan untuk tanggal {$today}.");
        }

        Log::info("[SyncBeverageStock] Selesai. Total: {$count} record.");

        return self::SUCCESS;
    }
}
