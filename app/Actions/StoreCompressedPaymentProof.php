<?php

namespace App\Actions;

use Illuminate\Http\UploadedFile;

final class StoreCompressedPaymentProof
{
    private const MAX_DIMENSION = 1600;

    private const WEBP_QUALITY = 82;

    public function __construct(private StoreCompressedImage $storeCompressedImage) {}

    public function execute(UploadedFile $proof): string
    {
        return $this->storeCompressedImage->execute(
            image: $proof,
            directory: 'membership-payment-proofs/'.now()->format('Y/m'),
            maxDimension: self::MAX_DIMENSION,
            webpQuality: self::WEBP_QUALITY,
            description: 'Bukti pembayaran',
        );
    }
}
