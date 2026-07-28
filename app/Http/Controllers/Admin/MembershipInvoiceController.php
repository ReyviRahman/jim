<?php

namespace App\Http\Controllers\Admin;

use App\Actions\BuildMembershipInvoiceData;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class MembershipInvoiceController extends Controller
{
    public function download(Membership $membership, BuildMembershipInvoiceData $buildInvoiceData): Response
    {
        $fileName = sprintf(
            'Invoice_Membership_%d_%s.pdf',
            $membership->id,
            str($membership->user?->name ?? 'Member')->slug('_'),
        );

        return Pdf::loadView('pages.dashboard.admin.riwayat.invoice-pdf', $buildInvoiceData->execute($membership))
            ->setPaper('a4')
            ->download($fileName);
    }
}
