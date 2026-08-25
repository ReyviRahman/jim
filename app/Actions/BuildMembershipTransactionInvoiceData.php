<?php

namespace App\Actions;

use App\Models\MembershipTransaction;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;

class BuildMembershipTransactionInvoiceData
{
    /**
     * @return array<string, mixed>
     */
    public function execute(
        MembershipTransaction $membershipTransaction,
        bool $includePaymentProof = false,
    ): array {
        $membershipTransaction->load([
            'user:id,name,phone,email',
            'admin:id,name',
            'membership.members:id,name',
        ]);

        $isMembershipTransaction = $membershipTransaction->membership_id !== null;
        $membershipMembers = $isMembershipTransaction
            ? ($membershipTransaction->membership?->members ?? collect())
            : collect();
        $members = collect([$membershipTransaction->user])
            ->merge($membershipMembers)
            ->filter()
            ->unique('id')
            ->values();
        $verificationUrl = URL::signedRoute('transaction.invoice.verify', [
            'membershipTransaction' => $membershipTransaction,
        ]);

        return [
            'membershipTransaction' => $membershipTransaction,
            'members' => $members,
            'isMembershipTransaction' => $isMembershipTransaction,
            'invoiceNumber' => $membershipTransaction->invoice_number,
            'invoiceDate' => $membershipTransaction->payment_date ?? $membershipTransaction->created_at,
            'paymentMethod' => str($membershipTransaction->payment_method)->upper()->toString(),
            'paymentStatusLabel' => 'TERBAYAR',
            'paymentStatusClass' => 'status-paid',
            'paymentProofDataUri' => $includePaymentProof
                ? $this->paymentProofDataUri($membershipTransaction->payment_proof_path)
                : null,
            'memberNumber' => sprintf('FG-%06d', $membershipTransaction->user_id),
            'detailHeading' => $isMembershipTransaction ? 'Detail Membership' : 'Detail Pemasukan',
            'detailLabel' => $isMembershipTransaction ? 'Paket' : 'Kategori',
            'detailName' => $membershipTransaction->package_name ?: '-',
            'logoDataUri' => $this->publicFileDataUri('image/png', 'icon.png'),
            'sectionIcons' => collect([
                'member' => 'member.svg',
                'membership' => 'membership.svg',
                'payment' => 'payment.svg',
            ])->mapWithKeys(fn (string $fileName, string $key): array => [
                $key => $this->publicFileDataUri('image/svg+xml', 'invoice-icons/'.$fileName),
            ]),
            'verificationUrl' => $verificationUrl,
            'qrCodeDataUri' => 'data:image/svg+xml;base64,'.base64_encode(
                (string) QrCode::format('svg')->size(160)->margin(1)->generate($verificationUrl),
            ),
        ];
    }

    private function publicFileDataUri(string $mimeType, string $path): string
    {
        return 'data:'.$mimeType.';base64,'.base64_encode(
            (string) file_get_contents(public_path($path)),
        );
    }

    private function paymentProofDataUri(?string $path): ?string
    {
        if (! is_string($path)
            || preg_match(
                '#\Amembership-payment-proofs/\d{4}/\d{2}/[A-Za-z0-9_-]+\.(?:jpe?g|png|webp)\z#i',
                $path,
            ) !== 1) {
            return null;
        }

        try {
            $disk = Storage::disk('public');

            if (! $disk->exists($path) || $disk->size($path) > 10 * 1024 * 1024) {
                return null;
            }

            $contents = $disk->get($path);

            if ($contents === '') {
                return null;
            }

            $imageInfo = @getimagesizefromstring($contents);
            $mimeType = is_array($imageInfo) ? ($imageInfo['mime'] ?? null) : null;

            if (! in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                return null;
            }

            return 'data:'.$mimeType.';base64,'.base64_encode($contents);
        } catch (Throwable) {
            return null;
        }
    }
}
