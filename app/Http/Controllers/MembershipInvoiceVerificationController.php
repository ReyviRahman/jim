<?php

namespace App\Http\Controllers;

use App\Actions\BuildMembershipInvoiceData;
use App\Models\Membership;
use Illuminate\Contracts\View\View;

class MembershipInvoiceVerificationController extends Controller
{
    /**
     * Display the signed public invoice verification page.
     */
    public function __invoke(Membership $membership, BuildMembershipInvoiceData $buildInvoiceData): View
    {
        return view('pages.membership-invoice-verification', $buildInvoiceData->execute($membership));
    }
}
