<?php

namespace App\Actions;

use App\Models\Beverage;
use App\Models\BeverageOperationalRequest;
use App\Models\BeverageSale;
use App\Models\DepositBeverage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class BeverageOperationalApproval
{
    public function submit(User $user, array $input): BeverageOperationalRequest
    {
        abort_unless(in_array($user->role, ['admin', 'kasir_gym', 'kasir_minum'], true), 403);
        $data = Validator::make($input, [
            'selected_products' => ['required', 'array', 'min:1', 'max:100'],
            'selected_products.*.beverage_id' => ['required', 'integer', 'distinct', 'exists:beverages,id'],
            'selected_products.*.jumlah_beli' => ['required', 'integer', 'min:1', 'max:100000'],
            'reason' => ['required', 'string', 'max:1000'],
            'keterangan_bayar' => ['required', 'in:operasional,deposit'],
            'selected_deposit_id' => ['required_if:keterangan_bayar,deposit', 'nullable', 'integer'],
        ])->validate();

        return DB::transaction(function () use ($user, $data): BeverageOperationalRequest {
            $products = Beverage::whereKey(collect($data['selected_products'])->pluck('beverage_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $items = collect($data['selected_products'])->map(function (array $item) use ($products): array {
                $product = $products->get($item['beverage_id']);
                if (! $product || $product->stok_sekarang < $item['jumlah_beli']) {
                    throw ValidationException::withMessages(['selected_products' => 'Produk tidak tersedia atau stok tidak mencukupi.']);
                }

                return ['beverage_id' => $product->id, 'nama_produk' => $product->nama_produk, 'harga_satuan' => $product->harga_jual, 'jumlah_beli' => (int) $item['jumlah_beli']];
            });
            $total = $items->sum(fn (array $item): int => $item['harga_satuan'] * $item['jumlah_beli']);
            $deposit = null;
            if ($data['keterangan_bayar'] === 'deposit') {
                $deposit = DepositBeverage::lockForUpdate()->find($data['selected_deposit_id']);
                if (! $deposit || $deposit->is_used || $deposit->sisa_nominal <= 0 || $deposit->sisa_nominal >= $total) {
                    throw ValidationException::withMessages(['selected_deposit_id' => 'Deposit harus aktif dan menyisakan pembayaran Operasional.']);
                }
            }
            $request = BeverageOperationalRequest::create([
                'requested_by' => $user->id, 'nama_staff' => $user->name,
                'shift' => $user->beverageShiftSnapshot(), 'reason' => trim($data['reason']),
                'status' => 'pending', 'requested_at' => now('Asia/Jakarta'),
                'total' => $total, 'deposit_beverage_id' => $deposit?->id,
                'deposit_amount' => $deposit?->sisa_nominal ?? 0,
            ]);
            $request->items()->createMany($items->all());
            if ($user->role === 'admin') {
                $this->approve($user, $request->id);
            }

            return $request->refresh();
        }, 3);
    }

    public function approve(User $admin, int $id): BeverageOperationalRequest
    {
        abort_unless($admin->role === 'admin', 403);

        return DB::transaction(function () use ($admin, $id): BeverageOperationalRequest {
            $request = BeverageOperationalRequest::lockForUpdate()->findOrFail($id);
            if ($request->status === 'approved') {
                return $request;
            }
            if ($request->status !== 'pending') {
                throw ValidationException::withMessages(['approval' => 'Pengajuan sudah ditolak.']);
            }
            $items = $request->items()->orderBy('id')->get();
            $quantities = $items->pluck('jumlah_beli', 'beverage_id');
            $impact = new BeverageStockImpact;
            $products = $impact->lockProducts($quantities);
            foreach ($quantities as $productId => $quantity) {
                $product = $products->get($productId);
                if (! $product || $product->trashed() || $product->stok_sekarang < $quantity) {
                    throw ValidationException::withMessages(['approval' => 'Produk tidak tersedia atau stok tidak mencukupi.']);
                }
            }
            $date = $request->requested_at->toDateString();
            if ($date < now('Asia/Jakarta')->toDateString()) {
                $inspection = $impact->inspect($quantities, $products, $date, -1);
                if ($inspection['error_count'] > 0) {
                    throw ValidationException::withMessages(['approval' => implode(' ', $inspection['errors'])]);
                }
            }
            $deposit = $request->deposit_beverage_id ? DepositBeverage::lockForUpdate()->find($request->deposit_beverage_id) : null;
            if ($request->deposit_amount > 0 && (! $deposit || $deposit->is_used || $deposit->sisa_nominal < $request->deposit_amount)) {
                throw ValidationException::withMessages(['approval' => 'Saldo deposit tidak lagi mencukupi.']);
            }
            $allocated = 0;
            $runningSubtotal = 0;
            foreach ($items as $item) {
                $subtotal = $item->harga_satuan * $item->jumlah_beli;
                $runningSubtotal += $subtotal;
                $share = (int) floor($request->deposit_amount * $runningSubtotal / max(1, $request->total)) - $allocated;
                $allocated += $share;
                BeverageSale::create([
                    'operational_request_id' => $request->id, 'beverage_id' => $item->beverage_id,
                    'nama_produk' => $item->nama_produk, 'nama_staff' => $request->nama_staff,
                    'waktu_transaksi' => $request->requested_at, 'shift' => $request->shift,
                    'jumlah_beli' => $item->jumlah_beli, 'harga_satuan' => $item->harga_satuan,
                    'total_harga' => $subtotal - $share, 'keterangan_bayar' => 'operasional',
                    'is_lunas' => true, 'deposit_beverage_id' => $deposit?->id,
                    'deposit_amount' => $deposit ? $share : null, 'nama_penghutang' => $deposit?->nama_pelanggan,
                ]);
            }
            $impact->apply($quantities, $date, -1);
            if ($deposit) {
                $remaining = $deposit->sisa_nominal - $request->deposit_amount;
                $deposit->update(['sisa_nominal' => $remaining, 'is_used' => $remaining === 0]);
            }
            $request->update(['status' => 'approved', 'decided_by' => $admin->id, 'decided_at' => now()]);

            return $request->refresh();
        }, 3);
    }

    public function reject(User $admin, int $id, string $reason): BeverageOperationalRequest
    {
        abort_unless($admin->role === 'admin', 403);
        Validator::make(['reason' => trim($reason)], ['reason' => ['required', 'string', 'max:1000']])->validate();

        return DB::transaction(function () use ($admin, $id, $reason): BeverageOperationalRequest {
            $request = BeverageOperationalRequest::lockForUpdate()->findOrFail($id);
            if ($request->status !== 'pending') {
                throw ValidationException::withMessages(['approval' => 'Pengajuan sudah diproses.']);
            }
            $request->update(['status' => 'rejected', 'decided_by' => $admin->id, 'decided_at' => now(), 'rejection_reason' => trim($reason)]);

            return $request->refresh();
        }, 3);
    }

    public function deletePending(User $user, int $id): void
    {
        abort_unless(in_array($user->role, ['admin', 'kasir_gym', 'kasir_minum'], true), 403);

        DB::transaction(function () use ($user, $id): void {
            $request = BeverageOperationalRequest::lockForUpdate()->findOrFail($id);
            abort_unless($user->role === 'admin' || $request->requested_by === $user->id, 403);

            if ($request->status !== 'pending') {
                throw ValidationException::withMessages(['approval' => 'Hanya pengajuan Menunggu yang dapat dihapus.']);
            }

            $request->items()->delete();
            $request->delete();
        }, 3);
    }
}
