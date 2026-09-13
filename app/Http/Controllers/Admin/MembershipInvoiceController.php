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

        $response = Pdf::loadView('pages.dashboard.admin.riwayat.invoice-pdf', $buildInvoiceData->execute($membership, includeWaivers: true))
            ->setPaper('a4')
            ->download($fileName);

        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }
}
