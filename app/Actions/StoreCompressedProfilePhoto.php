<?php

namespace App\Actions;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class StoreCompressedProfilePhoto
{
    private const MAX_DIMENSION = 800;

    private const WEBP_QUALITY = 82;

    public function execute(UploadedFile $photo): string
    {
        if (! extension_loaded('gd') || ! function_exists('imagewebp')) {
            throw new RuntimeException('Server tidak mendukung pemrosesan foto WebP.');
        }

        $sourcePath = $photo->getRealPath();
        $sourceContents = is_string($sourcePath) ? @file_get_contents($sourcePath) : false;

        if ($sourceContents === false) {
            throw new RuntimeException('Foto profil tidak dapat dibaca.');
        }

        $sourceImage = @imagecreatefromstring($sourceContents);

        if (! $sourceImage instanceof GdImage) {
            throw new RuntimeException('Format foto profil tidak dapat diproses.');
        }

        $targetImage = null;

        try {
            $sourceImage = $this->correctOrientation($sourceImage, $photo);
            [$targetWidth, $targetHeight] = $this->targetDimensions(
                imagesx($sourceImage),
                imagesy($sourceImage),
            );

            $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);

            if (! $targetImage instanceof GdImage) {
                throw new RuntimeException('Foto profil gagal dipersiapkan untuk kompresi.');
            }

            imagealphablending($targetImage, false);
            imagesavealpha($targetImage, true);
            $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
            imagefill($targetImage, 0, 0, $transparent);

            if (! imagecopyresampled(
                $targetImage,
                $sourceImage,
                0,
                0,
                0,
                0,
                $targetWidth,
                $targetHeight,
                imagesx($sourceImage),
                imagesy($sourceImage),
            )) {
                throw new RuntimeException('Foto profil gagal diperkecil.');
            }

            ob_start();
            $encoded = imagewebp($targetImage, null, self::WEBP_QUALITY);
            $webpContents = ob_get_clean();

            if (! $encoded || ! is_string($webpContents) || $webpContents === '') {
                throw new RuntimeException('Foto profil gagal dikompres ke WebP.');
            }

            $path = 'profile-photos/'.Str::uuid().'.webp';

            if (! Storage::disk('public')->put($path, $webpContents)) {
                throw new RuntimeException('Foto profil gagal disimpan.');
            }

            return $path;
        } finally {
            if ($targetImage instanceof GdImage) {
                imagedestroy($targetImage);
            }

            imagedestroy($sourceImage);
        }
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function targetDimensions(int $width, int $height): array
    {
        if ($width <= self::MAX_DIMENSION && $height <= self::MAX_DIMENSION) {
            return [$width, $height];
        }

        $scale = min(self::MAX_DIMENSION / $width, self::MAX_DIMENSION / $height);

        return [
            max(1, (int) round($width * $scale)),
            max(1, (int) round($height * $scale)),
        ];
    }

    private function correctOrientation(GdImage $image, UploadedFile $photo): GdImage
    {
        if ($photo->getMimeType() !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $sourcePath = $photo->getRealPath();
        $exif = is_string($sourcePath) ? @exif_read_data($sourcePath) : false;
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        return match ($orientation) {
            2 => $this->flip($image, IMG_FLIP_HORIZONTAL),
            3 => $this->rotate($image, 180),
            4 => $this->flip($image, IMG_FLIP_VERTICAL),
            5 => $this->rotate($this->flip($image, IMG_FLIP_HORIZONTAL), 90),
            6 => $this->rotate($image, -90),
            7 => $this->rotate($this->flip($image, IMG_FLIP_HORIZONTAL), -90),
            8 => $this->rotate($image, 90),
            default => $image,
        };
    }

    private function flip(GdImage $image, int $mode): GdImage
    {
        if (! imageflip($image, $mode)) {
            throw new RuntimeException('Orientasi foto profil gagal diperbaiki.');
        }

        return $image;
    }

    private function rotate(GdImage $image, float $angle): GdImage
    {
        $rotatedImage = imagerotate($image, $angle, 0);

        if (! $rotatedImage instanceof GdImage) {
            throw new RuntimeException('Orientasi foto profil gagal diperbaiki.');
        }

        imagedestroy($image);

        return $rotatedImage;
    }
}
