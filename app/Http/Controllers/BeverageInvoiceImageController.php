<?php

namespace App\Http\Controllers;

use App\Models\BeverageInvoice;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BeverageInvoiceImageController extends Controller
{
    public function __invoke(BeverageInvoice $invoice): StreamedResponse
    {
        abort_unless(auth()->check() && in_array(auth()->user()->role, ['admin', 'kasir_gym', 'kasir_minum'], true), 403);
        abort_unless($invoice->image_path && Storage::disk('local')->exists($invoice->image_path), 404);

        return Storage::disk('local')->response($invoice->image_path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
