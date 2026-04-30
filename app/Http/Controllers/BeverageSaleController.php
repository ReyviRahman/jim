<?php

namespace App\Http\Controllers;

use App\Exports\BeverageSaleExport;
use App\Models\BeverageSale;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class BeverageSaleController extends Controller
{
    public function export(Request $request)
    {
        $searchProduct = $request->get('search', '');
        $start_date = $request->get('start_date', '');
        $end_date = $request->get('end_date', '');

        $fileName = 'penjualan_minuman_' . date('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new BeverageSaleExport($searchProduct, $start_date, $end_date),
            $fileName
        );
    }
}