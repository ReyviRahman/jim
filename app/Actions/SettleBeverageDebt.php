<?php

namespace App\Actions;

use App\Models\BeverageSale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class SettleBeverageDebt
{
    public function execute(int $id, string $method): bool
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'kasir_gym', 'kasir_minum'], true), 403);
        Validator::make(['method' => $method], ['method' => ['required', Rule::in(DeleteBeverageSale::SETTLEMENT_METHODS)]])->validate();

        return DB::transaction(function () use ($id, $method): bool {
            $sale = BeverageSale::query()->lockForUpdate()->find($id);
            if (! $sale || $sale->keterangan_bayar !== 'hutang' || $sale->parent_beverage_sale_id || $sale->is_lunas) {
                return false;
            }
            BeverageSale::create([
                'beverage_id' => $sale->beverage_id, 'parent_beverage_sale_id' => $sale->id,
                'nama_produk' => $sale->nama_produk, 'nama_staff' => $sale->nama_staff,
                'waktu_transaksi' => now(), 'shift' => $sale->shift, 'jumlah_beli' => $sale->jumlah_beli,
                'harga_satuan' => $sale->harga_satuan, 'total_harga' => $sale->total_harga,
                'keterangan_bayar' => $method, 'nama_penghutang' => $sale->nama_penghutang, 'is_lunas' => true,
            ]);
            $sale->update(['is_lunas' => true]);

            return true;
        }, 3);
    }
}
