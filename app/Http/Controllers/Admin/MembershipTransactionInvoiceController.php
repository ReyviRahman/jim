<?php

namespace App\Http\Controllers\Admin;

use App\Actions\BuildMembershipTransactionInvoiceData;
use App\Http\Controllers\Controller;
use App\Models\MembershipTransaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class MembershipTransactionInvoiceController extends Controller
{
    public function download(
        MembershipTransaction $membershipTransaction,
        BuildMembershipTransactionInvoiceData $buildInvoiceData,
    ): Response {
        $invoiceSlug = Str::slug($membershipTransaction->invoice_number, '_') ?: (string) $membershipTransaction->id;
        $response = Pdf::loadView(
            'pages.dashboard.admin.penjualan.invoice-pdf',
            $buildInvoiceData->execute($membershipTransaction, includePaymentProof: true),
        )->setPaper('a4')->download('Invoice_Transaksi_'.$invoiceSlug.'.pdf');

        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }
}
