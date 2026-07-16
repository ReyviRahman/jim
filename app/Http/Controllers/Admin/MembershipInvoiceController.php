<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class MembershipInvoiceController extends Controller
{
    public function download(Membership $membership): Response
    {
        $membership->load([
            'user',
            'members',
            'admin',
            'personalTrainer',
            'gymPackage',
            'ptPackage',
            'transactions' => fn ($query) => $query->with('admin')->orderBy('payment_date')->orderBy('id'),
        ]);

        $members = collect([$membership->user])
            ->merge($membership->members)
            ->filter()
            ->unique('id')
            ->values();

        $fileName = sprintf(
            'Invoice_Membership_%d_%s.pdf',
            $membership->id,
            str($membership->user?->name ?? 'Member')->slug('_'),
        );

        return Pdf::loadView('pages.dashboard.admin.riwayat.invoice-pdf', [
            'membership' => $membership,
            'members' => $members,
            'remainingBalance' => max(0, (float) $membership->price_paid - (float) $membership->total_paid),
        ])->setPaper('a4')->download($fileName);
    }
}
