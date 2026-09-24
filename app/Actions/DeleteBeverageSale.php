<?php

namespace App\Actions;

use App\Models\BeverageSale;
use App\Models\DepositBeverage;
use Illuminate\Support\Facades\DB;

final class DeleteBeverageSale
{
    public const SETTLEMENT_METHODS = ['deposit_hutang_cash', 'deposit_hutang_qris'];

    public function __construct(private BeverageStockImpact $impact) {}

    /** @return array<string, mixed>|null */
    public function preview(int $id): ?array
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return DB::transaction(fn () => $this->inspect($id)['preview'] ?? null, 3);
    }

    /** Null means deleted or already missing; an array requires another review.
     * @return array<string, mixed>|null
     */
    public function execute(int $id, string $fingerprint): ?array
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return DB::transaction(function () use ($id, $fingerprint): ?array {
            $context = $this->inspect($id);
            if ($context === null) {
                return null;
            }
            $preview = $context['preview'];
            if ($preview['error_count'] || ! hash_equals($preview['fingerprint'], $fingerprint)) {
                return $preview;
            }
            if ($preview['stock_affecting']) {
                $this->impact->apply(collect([$context['sale']->beverage_id => $context['sale']->jumlah_beli]), $preview['date'], 1);
            }
            foreach ($preview['deposit_returns'] as $refund) {
                $context['deposits']->get($refund['id'])->update(['sisa_nominal' => $refund['after'], 'is_used' => $refund['after'] <= 0]);
            }
            foreach ($preview['deposit_removals'] as $removal) {
                $context['deposits']->get($removal['id'])->delete();
            }
            foreach ($context['deleting']->sortByDesc('id') as $row) {
                $row->delete();
            }
            if ($preview['parent']) {
                $context['root']->update(['is_lunas' => $preview['parent']['is_lunas_after']]);
            }

            return null;
        }, 3);
    }

    /** @return array<string, mixed>|null */
    private function inspect(int $id): ?array
    {
        $hint = BeverageSale::find($id);
        if (! $hint) {
            return null;
        }
        $root = BeverageSale::query()->lockForUpdate()->find($hint->parent_beverage_sale_id ?? $id);
        if (! $root) {
            return null;
        }
        $children = BeverageSale::query()->where('parent_beverage_sale_id', $root->id)->orderBy('id')->lockForUpdate()->get();
        $family = collect([$root])->concat($children);
        $sale = $family->firstWhere('id', $id);
        if (! $sale) {
            return null;
        }
        $deleting = $sale->id === $root->id ? $family : collect([$sale]);
        $stockAffecting = ! in_array($sale->keterangan_bayar, [...self::SETTLEMENT_METHODS, 'pengeluaran_umum'], true);
        $date = $sale->waktu_transaksi->copy()->setTimezone('Asia/Jakarta')->toDateString();
        $preview = ['date' => $date, 'through' => now('Asia/Jakarta')->toDateString(), 'products' => [], 'errors' => [], 'error_count' => 0, 'affected_count' => 0, 'fingerprint' => ''];
        if ($stockAffecting && $sale->jumlah_beli > 0 && $date <= $preview['through']) {
            $quantities = collect([(int) $sale->beverage_id => $sale->jumlah_beli]);
            $preview = $this->impact->inspect($quantities, $this->impact->lockProducts($quantities), $date, 1);
        } elseif ($stockAffecting) {
            $preview['errors'][] = $sale->nama_produk.' '.$date.': jumlah harus positif dan tanggal transaksi tidak boleh melewati hari ini.';
        }
        if ($children->contains(fn (BeverageSale $child) => ! in_array($child->keterangan_bayar, self::SETTLEMENT_METHODS, true))) {
            $preview['errors'][] = 'Transaksi terkait bukan catatan pelunasan hutang. Periksa data sebelum menghapus.';
        }

        $refunds = $deleting->where('deposit_amount', '>', 0)->groupBy('deposit_beverage_id')->map(fn ($rows) => (int) $rows->sum('deposit_amount'));
        $deposits = DepositBeverage::query()->where(function ($query) use ($refunds, $deleting): void {
            $query->whereIn('id', $refunds->keys())->orWhereIn('beverage_sale_id', $deleting->pluck('id'));
        })->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $preview['deposit_returns'] = [];
        foreach ($refunds as $depositId => $amount) {
            $deposit = $deposits->get($depositId);
            if (! $deposit || $deposit->sisa_nominal + $amount > $deposit->nominal) {
                $preview['errors'][] = 'Deposit #'.$depositId.' tidak tersedia atau jumlah pengembaliannya tidak valid.';

                continue;
            }
            $preview['deposit_returns'][] = ['id' => $deposit->id, 'customer' => $deposit->nama_pelanggan, 'amount' => $amount, 'before' => $deposit->sisa_nominal, 'after' => $deposit->sisa_nominal + $amount];
        }
        $preview['deposit_removals'] = [];
        foreach ($deposits->whereIn('beverage_sale_id', $deleting->pluck('id')) as $deposit) {
            if ($deposit->is_used || $deposit->sisa_nominal !== $deposit->nominal) {
                $preview['errors'][] = 'Deposit kembalian '.$deposit->nama_pelanggan.' sudah digunakan. Transaksi tidak dapat dihapus.';
            }
            $preview['deposit_removals'][] = ['id' => $deposit->id, 'customer' => $deposit->nama_pelanggan, 'amount' => $deposit->nominal];
        }
        $preview['parent'] = $sale->id !== $root->id ? [
            'id' => $root->id,
            'is_lunas_after' => $children->where('id', '!=', $sale->id)->whereIn('keterangan_bayar', self::SETTLEMENT_METHODS)->isNotEmpty(),
        ] : null;
        $preview['error_count'] = max($preview['error_count'], count($preview['errors']));
        $preview['fingerprint'] = hash('sha256', $id.'|'.$preview['fingerprint'].'|'.$family->toJson().'|'.$deposits->toJson().'|'.json_encode($preview));
        $preview += ['sale_id' => $sale->id, 'name' => $sale->nama_produk, 'method' => $sale->keterangan_bayar, 'pcs' => $stockAffecting ? $sale->jumlah_beli : 0, 'stock_affecting' => $stockAffecting, 'settlement_count' => $deleting->count() - 1];

        return compact('preview', 'sale', 'root', 'deleting', 'deposits');
    }
}
