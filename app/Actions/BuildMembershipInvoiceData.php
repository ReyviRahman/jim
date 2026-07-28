<?php

namespace App\Actions;

use App\Models\Membership;
use Illuminate\Support\Facades\URL;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class BuildMembershipInvoiceData
{
    /**
     * @return array<string, mixed>
     */
    public function execute(Membership $membership): array
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

        $latestTransaction = $membership->transactions->last();
        $verificationUrl = URL::signedRoute('membership.invoice.verify', [
            'membership' => $membership,
        ]);

        return [
            'membership' => $membership,
            'members' => $members,
            'invoiceNumber' => $latestTransaction?->invoice_number ?? sprintf('MEM-%06d', $membership->id),
            'invoiceDate' => $latestTransaction?->payment_date ?? $membership->created_at,
            'paymentMethod' => $latestTransaction?->payment_method
                ? str($latestTransaction->payment_method)->upper()->toString()
                : '-',
            'paymentStatusLabel' => match ($membership->payment_status) {
                'paid' => 'LUNAS',
                'partial' => 'SEBAGIAN',
                default => 'BELUM LUNAS',
            },
            'paymentStatusClass' => match ($membership->payment_status) {
                'paid' => 'status-paid',
                'partial' => 'status-partial',
                default => 'status-unpaid',
            },
            'membershipStatusLabel' => match ($membership->status) {
                'active' => 'AKTIF',
                'completed' => 'SELESAI',
                'rejected' => 'DITOLAK',
                default => 'MENUNGGU',
            },
            'memberNumber' => sprintf('FG-%06d', $membership->user_id),
            'packageName' => collect([
                $membership->gymPackage?->name,
                $membership->ptPackage?->name,
            ])->filter()->join(' / ') ?: '-',
            'membershipEndDate' => collect([
                $membership->membership_end_date?->locale('id')->translatedFormat('d F Y'),
                $membership->pt_end_date?->locale('id')->translatedFormat('d F Y'),
            ])->filter()->unique()->join(' / ') ?: '-',
            'logoDataUri' => $this->publicFileDataUri('image/png', 'icon.png'),
            'sectionIcons' => collect([
                'member' => 'member.svg',
                'membership' => 'membership.svg',
                'payment' => 'payment.svg',
                'history' => 'history.svg',
            ])->mapWithKeys(fn (string $fileName, string $key): array => [
                $key => $this->publicFileDataUri('image/svg+xml', 'invoice-icons/'.$fileName),
            ]),
            'verificationUrl' => $verificationUrl,
            'qrCodeDataUri' => 'data:image/svg+xml;base64,'.base64_encode(
                (string) QrCode::format('svg')->size(160)->margin(1)->generate($verificationUrl),
            ),
            'remainingBalance' => max(0, (float) $membership->price_paid - (float) $membership->total_paid),
        ];
    }

    private function publicFileDataUri(string $mimeType, string $path): string
    {
        return 'data:'.$mimeType.';base64,'.base64_encode(
            (string) file_get_contents(public_path($path)),
        );
    }
}
