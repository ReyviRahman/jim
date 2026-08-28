<?php

namespace App\Actions;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class StoreCompressedImage
{
    public function execute(
        UploadedFile $image,
        string $directory,
        int $maxDimension,
        int $webpQuality,
        string $description,
    ): string {
        if (! extension_loaded('gd') || ! function_exists('imagewebp')) {
            throw new RuntimeException('Server tidak mendukung pemrosesan gambar WebP.');
        }

        $sourcePath = $image->getRealPath();
        $sourceContents = is_string($sourcePath) ? @file_get_contents($sourcePath) : false;

        if ($sourceContents === false) {
            throw new RuntimeException($description.' tidak dapat dibaca.');
        }

        $sourceImage = @imagecreatefromstring($sourceContents);

        if (! $sourceImage instanceof GdImage) {
            throw new RuntimeException('Format '.Str::lower($description).' tidak dapat diproses.');
        }

        $targetImage = null;

        try {
            $sourceImage = $this->correctOrientation($sourceImage, $image);
            [$targetWidth, $targetHeight] = $this->targetDimensions(
                imagesx($sourceImage),
                imagesy($sourceImage),
                $maxDimension,
            );

            $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);

            if (! $targetImage instanceof GdImage) {
                throw new RuntimeException($description.' gagal dipersiapkan untuk kompresi.');
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
                throw new RuntimeException($description.' gagal diperkecil.');
            }

            ob_start();
            $encoded = imagewebp($targetImage, null, $webpQuality);
            $webpContents = ob_get_clean();

            if (! $encoded || ! is_string($webpContents) || $webpContents === '') {
                throw new RuntimeException($description.' gagal dikompres ke WebP.');
            }

            $path = trim($directory, '/').'/'.Str::uuid().'.webp';

            if (! Storage::disk('public')->put($path, $webpContents)) {
                throw new RuntimeException($description.' gagal disimpan.');
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
    private function targetDimensions(int $width, int $height, int $maxDimension): array
    {
        if ($width <= $maxDimension && $height <= $maxDimension) {
            return [$width, $height];
        }

        $scale = min($maxDimension / $width, $maxDimension / $height);

        return [
            max(1, (int) round($width * $scale)),
            max(1, (int) round($height * $scale)),
        ];
    }

    private function correctOrientation(GdImage $sourceImage, UploadedFile $image): GdImage
    {
        if ($image->getMimeType() !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $sourceImage;
        }

        $sourcePath = $image->getRealPath();
        $exif = is_string($sourcePath) ? @exif_read_data($sourcePath) : false;
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        return match ($orientation) {
            2 => $this->flip($sourceImage, IMG_FLIP_HORIZONTAL),
            3 => $this->rotate($sourceImage, 180),
            4 => $this->flip($sourceImage, IMG_FLIP_VERTICAL),
            5 => $this->rotate($this->flip($sourceImage, IMG_FLIP_HORIZONTAL), 90),
            6 => $this->rotate($sourceImage, -90),
            7 => $this->rotate($this->flip($sourceImage, IMG_FLIP_HORIZONTAL), -90),
            8 => $this->rotate($sourceImage, 90),
            default => $sourceImage,
        };
    }

    private function flip(GdImage $image, int $mode): GdImage
    {
        if (! imageflip($image, $mode)) {
            throw new RuntimeException('Orientasi gambar gagal diperbaiki.');
        }

        return $image;
    }

    private function rotate(GdImage $image, float $angle): GdImage
    {
        $rotatedImage = imagerotate($image, $angle, 0);

        if (! $rotatedImage instanceof GdImage) {
            throw new RuntimeException('Orientasi gambar gagal diperbaiki.');
        }

        imagedestroy($image);

        return $rotatedImage;
    }
}
