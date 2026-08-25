<?php

namespace App\Http\Controllers;

use App\Actions\BuildMembershipTransactionInvoiceData;
use App\Models\MembershipTransaction;
use Illuminate\Http\Response;

class MembershipTransactionInvoiceVerificationController extends Controller
{
    public function __invoke(
        MembershipTransaction $membershipTransaction,
        BuildMembershipTransactionInvoiceData $buildInvoiceData,
    ): Response {
        return response()
            ->view(
                'pages.membership-transaction-invoice-verification',
                $buildInvoiceData->execute($membershipTransaction),
            )
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
