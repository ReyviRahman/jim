<?php

namespace App\Actions;

use App\Models\Beverage;
use App\Models\BeverageStokSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class BeverageStockImpact
{
    /** @param Collection<int, int> $quantities
     * @return Collection<int, Beverage>
     */
    public function lockProducts(Collection $quantities): Collection
    {
        return Beverage::withTrashed()->whereKey($quantities->keys())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    /** @param Collection<int, int> $quantities
     * @param  Collection<int, Beverage>  $products
     * @return array{date: string, through: string, products: array, errors: array, error_count: int, affected_count: int, fingerprint: string}
     */
    public function inspect(Collection $quantities, Collection $products, string $date, int $sign): array
    {
        $today = CarbonImmutable::now('Asia/Jakarta')->toDateString();
        $errors = [];
        $errorCount = 0;
        $addError = function (string $message) use (&$errors, &$errorCount): void {
            $errorCount++;
            if (count($errors) < 20) {
                $errors[] = $message;
            }
        };
        $hash = hash_init('sha256');
        hash_update($hash, $date.'|'.$today.'|'.$sign);
        $summary = [];
        $affectedCount = 0;
        foreach ($quantities->sortKeys() as $id => $quantity) {
            $product = $products->get($id);
            if (! $product) {
                $addError('Produk #'.$id.' tidak ditemukan.');

                continue;
            }
            $delta = $sign * $quantity;
            $after = $product->stok_sekarang + $delta;
            $summary[] = ['id' => $id, 'name' => $product->nama_produk, 'pcs' => $quantity, 'before' => $product->stok_sekarang, 'after' => $after];
            hash_update($hash, json_encode([$id, $quantity, $product->stok_sekarang]));
            if ($after < 0) {
                $addError($product->nama_produk.': stok sekarang akan menjadi '.$after.'.');
            }
            $required = [];
            for ($day = CarbonImmutable::parse($date); $day->toDateString() <= $today; $day = $day->addDay()) {
                $required[$day->toDateString().'|init'] = true;
                if ($day->toDateString() < $today) {
                    $required[$day->toDateString().'|last'] = true;
                }
            }
            foreach (BeverageStokSnapshot::query()->where('beverage_id', $id)->where('tanggal', '>=', $date)->orderBy('tanggal')->orderBy('tipe')->cursor() as $snapshot) {
                $snapshotDate = $snapshot->tanggal->toDateString();
                unset($required[$snapshotDate.'|'.$snapshot->tipe]);
                hash_update($hash, json_encode([$snapshot->id, $snapshotDate, $snapshot->tipe, $snapshot->jumlah]));
                if ($snapshotDate > $date || $snapshot->tipe === 'last') {
                    $affectedCount++;
                    if ($snapshot->jumlah + $delta < 0) {
                        $addError($product->nama_produk.': stok '.$snapshot->tipe.' '.$snapshotDate.' akan negatif.');
                    }
                }
            }
            foreach (array_keys($required) as $missing) {
                [$day, $type] = explode('|', $missing);
                $addError($product->nama_produk.': snapshot '.$type.' '.$day.' belum tersedia.');
                hash_update($hash, $missing);
            }
        }

        return ['date' => $date, 'through' => $today, 'products' => $summary, 'errors' => $errors, 'error_count' => $errorCount, 'affected_count' => $affectedCount, 'fingerprint' => hash_final($hash)];
    }

    /** @param array<int> $ids */
    public function snapshots(array $ids, string $date): Builder
    {
        return BeverageStokSnapshot::query()->whereIn('beverage_id', $ids)
            ->where(function (Builder $query) use ($date): void {
                $query->where('tanggal', '>', $date)
                    ->orWhere(fn (Builder $query) => $query->where('tanggal', $date)->where('tipe', 'last'));
            });
    }

    /** @param Collection<int, int> $quantities */
    public function apply(Collection $quantities, string $date, int $sign): void
    {
        foreach ($quantities->sortKeys() as $id => $quantity) {
            Beverage::withTrashed()->whereKey($id)->increment('stok_sekarang', $quantity * $sign);
            $this->snapshots([$id], $date)->increment('jumlah', $quantity * $sign);
        }
    }
}
