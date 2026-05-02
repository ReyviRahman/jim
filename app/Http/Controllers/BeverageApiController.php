<?php

namespace App\Http\Controllers;

use App\Models\Beverage;
use App\Models\BeverageSale;
use Carbon\Carbon;
use Illuminate\Http\Request;
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

        $namaStaff = $request->nama_staff;
        $shift = $request->shift;
        $keteranganBayar = $request->keterangan_bayar;
        $tanggal = $request->tanggal ? Carbon::parse($request->tanggal)->setTimezone('Asia/Jakarta') : now()->setTimezone('Asia/Jakarta');
        $isHutang = $keteranganBayar === 'hutang';
        $namaPenghutang = $isHutang ? $request->nama_penghutang : null;
        $isLunas = $isHutang ? false : true;

        foreach ($selectedProducts as $item) {
            BeverageSale::create([
                'beverage_id' => $item['beverage_id'],
                'nama_produk' => $item['nama_produk'],
                'nama_staff' => $namaStaff,
                'waktu_transaksi' => $tanggal,
                'shift' => $shift,
                'jumlah_beli' => $item['jumlah_beli'],
                'harga_satuan' => $item['harga_satuan'],
                'total_harga' => $item['harga_satuan'] * $item['jumlah_beli'],
                'keterangan_bayar' => $keteranganBayar,
                'nama_penghutang' => $namaPenghutang,
                'is_lunas' => $isLunas,
            ]);

            $beverage = Beverage::find($item['beverage_id']);
            $beverage->update([
                'stok_sekarang' => $beverage->stok_sekarang - $item['jumlah_beli'],
            ]);
        }

        $total = collect($selectedProducts)->sum(fn ($item) => $item['harga_satuan'] * $item['jumlah_beli']);
        Session::flash('success', 'Transaksi berhasil disimpan! Total: Rp '.number_format($total, 0, ',', '.'));

        return redirect()->back();
    }
}
