<?php

namespace App\Http\Controllers;

use App\Models\Beverage;
use App\Models\BeverageSale;
use App\Models\DepositBeverage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class BeverageApiController extends Controller
{
    public function search(Request $request)
    {
        $q = $request->get('q', '');

        if (strlen($q) < 1) {
            return response()->json([]);
        }

        $beverages = Beverage::where('stok_sekarang', '>', 0)
            ->where('nama_produk', 'like', '%'.$q.'%')
            ->orderBy('nama_produk')
            ->limit(10)
            ->get(['id', 'nama_produk', 'harga_jual', 'stok_sekarang']);

        return response()->json($beverages);
    }

    public function activeDeposits(Request $request)
    {
        $q = $request->get('q', '');

        $deposits = DepositBeverage::where('is_used', false)
            ->where('sisa_nominal', '>', 0)
            ->when($q, function ($query) use ($q) {
                $query->where('nama_pelanggan', 'like', '%'.$q.'%');
            })
            ->orderBy('nama_pelanggan')
            ->limit(5)
            ->get(['id', 'nama_pelanggan', 'nominal', 'sisa_nominal']);

        return response()->json($deposits);
    }

    public function processSale(Request $request)
    {
        $selectedProducts = json_decode($request->selected_products, true);

        if (empty($selectedProducts)) {
            Session::flash('error', 'Pilih produk terlebih dahulu.');

            return redirect()->back();
        }

        if ($request->keterangan_bayar === 'hutang' && empty($request->nama_penghutang)) {
            Session::flash('error', 'Nama penghutang harus diisi untuk transaksi hutang.');

            return redirect()->back();
        }

        $keteranganBayar = $request->keterangan_bayar;
        $total = collect($selectedProducts)->sum(fn ($item) => $item['harga_satuan'] * $item['jumlah_beli']);

        if ($keteranganBayar === 'cash') {
            $cashReceived = (int) str_replace('.', '', $request->cash_received);
            if ($cashReceived < $total) {
                Session::flash('error', 'Nominal cash yang diterima harus lebih besar atau sama dengan total belanja.');

                return redirect()->back();
            }

            if ($request->save_change_as_deposit && empty($request->deposit_customer_name)) {
                Session::flash('error', 'Nama pelanggan harus diisi jika kembalian disimpan sebagai deposit.');

                return redirect()->back();
            }
        }

        $deposit = null;
        $depositUsed = 0;
        $remainingTotal = 0;
        $paymentMethodForProducts = $keteranganBayar;
        $namaPenghutangForProducts = null;

        if ($keteranganBayar === 'deposit') {
            if (empty($request->selected_deposit_id)) {
                Session::flash('error', 'Pilih deposit yang akan digunakan.');

                return redirect()->back();
            }

            $deposit = DepositBeverage::where('id', $request->selected_deposit_id)
                ->where('is_used', false)
                ->where('sisa_nominal', '>', 0)
                ->first();

            if (! $deposit) {
                Session::flash('error', 'Deposit tidak ditemukan atau sudah digunakan.');

                return redirect()->back();
            }

            $depositUsed = min($deposit->sisa_nominal, $total);
            $remainingTotal = $total - $depositUsed;

            if ($remainingTotal > 0) {
                if (empty($request->secondary_payment_method)) {
                    Session::flash('error', 'Pilih metode pembayaran untuk sisa total bayar.');

                    return redirect()->back();
                }

                $paymentMethodForProducts = $request->secondary_payment_method;

                if ($paymentMethodForProducts === 'cash') {
                    $secondaryCashReceived = (int) str_replace('.', '', $request->secondary_cash_received);
                    if ($secondaryCashReceived < $remainingTotal) {
                        Session::flash('error', 'Nominal cash untuk sisa pembayaran harus lebih besar atau sama dengan Rp '.number_format($remainingTotal, 0, ',', '.').'.');

                        return redirect()->back();
                    }
                }

                if ($paymentMethodForProducts === 'hutang') {
                    if (empty($request->secondary_nama_penghutang)) {
                        Session::flash('error', 'Nama penghutang harus diisi untuk sisa pembayaran hutang.');

                        return redirect()->back();
                    }
                    $namaPenghutangForProducts = $request->secondary_nama_penghutang;
                }
            }
        }

        $namaStaff = $request->nama_staff;
        $shift = $request->shift;
        $tanggal = $request->tanggal ? Carbon::parse($request->tanggal)->setTimezone('Asia/Jakarta') : now()->setTimezone('Asia/Jakarta');
        $isHutang = $keteranganBayar === 'hutang';
        $isSplitHutang = $keteranganBayar === 'deposit' && $paymentMethodForProducts === 'hutang';
        $isLunas = ! $isHutang && ! $isSplitHutang;

        if ($isHutang) {
            $namaPenghutangForProducts = $request->nama_penghutang;
        } elseif ($keteranganBayar === 'deposit' && ! $isSplitHutang && $deposit) {
            $namaPenghutangForProducts = $deposit->nama_pelanggan;
        }

        DB::transaction(function () use ($selectedProducts, $namaStaff, $shift, $tanggal, $keteranganBayar, $paymentMethodForProducts, $namaPenghutangForProducts, $isLunas, $total, $remainingTotal, $depositUsed, $request, $deposit) {
            $firstSale = null;
            $isSplitDeposit = $keteranganBayar === 'deposit' && $depositUsed > 0 && $remainingTotal > 0;
            $allocatedDeposit = 0;

            foreach ($selectedProducts as $index => $item) {
                $itemSubtotal = $item['harga_satuan'] * $item['jumlah_beli'];
                $itemTotal = $itemSubtotal;
                $itemProductName = $item['nama_produk'];
                $itemDepositAmount = null;

                if ($isSplitDeposit) {
                    $isLastItem = $index === count($selectedProducts) - 1;
                    $itemDepositShare = $isLastItem
                        ? $depositUsed - $allocatedDeposit
                        : (int) round($depositUsed * ($itemSubtotal / $total));
                    $allocatedDeposit += $itemDepositShare;
                    $itemTotal = $itemSubtotal - $itemDepositShare;
                    $itemProductName = $item['nama_produk'].' (dipotong deposit Rp '.number_format($itemDepositShare, 0, ',', '.').')';
                    $itemDepositAmount = $itemDepositShare;
                } elseif ($keteranganBayar === 'deposit' && $deposit) {
                    $itemDepositAmount = $itemSubtotal;
                }

                $sale = BeverageSale::create([
                    'beverage_id' => $item['beverage_id'],
                    'deposit_beverage_id' => $deposit?->id,
                    'deposit_amount' => $itemDepositAmount,
                    'nama_produk' => $itemProductName,
                    'nama_staff' => $namaStaff,
                    'waktu_transaksi' => $tanggal,
                    'shift' => $shift,
                    'jumlah_beli' => $item['jumlah_beli'],
                    'harga_satuan' => $item['harga_satuan'],
                    'total_harga' => $itemTotal,
                    'keterangan_bayar' => $paymentMethodForProducts,
                    'nama_penghutang' => $namaPenghutangForProducts,
                    'is_lunas' => $isLunas,
                ]);

                if ($firstSale === null) {
                    $firstSale = $sale;
                }

                $beverage = Beverage::find($item['beverage_id']);
                $beverage->update([
                    'stok_sekarang' => $beverage->stok_sekarang - $item['jumlah_beli'],
                ]);
            }

            if ($keteranganBayar === 'cash' && $request->save_change_as_deposit) {
                $cashReceived = (int) str_replace('.', '', $request->cash_received);
                $changeAmount = $cashReceived - $total;

                if ($changeAmount > 0 && $firstSale) {
                    DepositBeverage::create([
                        'beverage_sale_id' => $firstSale->id,
                        'nama_pelanggan' => $request->deposit_customer_name,
                        'nominal' => $changeAmount,
                        'sisa_nominal' => $changeAmount,
                        'is_used' => false,
                    ]);

                    $firstSale->update([
                        'nama_penghutang' => $request->deposit_customer_name,
                        'save_deposit' => $changeAmount,
                    ]);
                }
            }

            if ($keteranganBayar === 'deposit' && $paymentMethodForProducts === 'cash' && $remainingTotal > 0 && $request->save_secondary_change_as_deposit) {
                $secondaryCashReceived = (int) str_replace('.', '', $request->secondary_cash_received);
                $changeAmount = $secondaryCashReceived - $remainingTotal;

                if ($changeAmount > 0 && $firstSale) {
                    DepositBeverage::create([
                        'beverage_sale_id' => $firstSale->id,
                        'nama_pelanggan' => $request->secondary_deposit_customer_name,
                        'nominal' => $changeAmount,
                        'sisa_nominal' => $changeAmount,
                        'is_used' => false,
                    ]);

                    $firstSale->update([
                        'nama_penghutang' => $request->secondary_deposit_customer_name,
                        'save_deposit' => $changeAmount,
                    ]);
                }
            }

            if ($keteranganBayar === 'deposit' && $deposit) {
                $remaining = $deposit->sisa_nominal - $depositUsed;
                $deposit->update([
                    'sisa_nominal' => $remaining,
                    'is_used' => $remaining <= 0,
                ]);
            }
        });

        Session::flash('success', 'Transaksi berhasil disimpan! Total: Rp '.number_format($total, 0, ',', '.'));

        return redirect()->back();
    }
}
