<?php

namespace App\Domain\Galleries;

use InvalidArgumentException;
use RuntimeException;

class GalleryImageConverter
{
    /**
     * @return array{contents: string, width: int, height: int, file_size: int, sha256: string}
     */
    public function toWebp(string $contents): array
    {
        $this->details($contents);

        if ($this->imagickAvailable()) {
            return $this->withImagick($contents);
        }

        if ($this->gdAvailable()) {
            return $this->withGd($contents);
        }

        throw new RuntimeException('Gallery photos require PHP GD with WebP support or ImageMagick with WebP support.');
    }

    /**
     * @return array{mime_type: string, width: int, height: int}
     */
    public function details(string $contents): array
    {
        $details = @getimagesizefromstring($contents);
        $mimeType = $details['mime'] ?? null;

        if (! is_array($details) || ! is_string($mimeType) || ! in_array($mimeType, config('gallery.allowed_mime_types', []), true)) {
            throw new InvalidArgumentException('The uploaded file is not a supported JPEG, PNG, or WebP image.');
        }

        $width = (int) $details[0];
        $height = (int) $details[1];
        $maximumPixels = max(1, (int) config('gallery.max_megapixels', 50)) * 1_000_000;

        if ($width < 1 || $height < 1 || ($width * $height) > $maximumPixels) {
            throw new InvalidArgumentException('The image dimensions are invalid or exceed the configured megapixel limit.');
        }

        return [
            'mime_type' => $mimeType,
            'width' => $width,
            'height' => $height,
        ];
    }

    public function canConvert(): bool
    {
        return $this->imagickAvailable() || $this->gdAvailable();
    }

    private function imagickAvailable(): bool
    {
        if (! class_exists(\Imagick::class)) {
            return false;
        }

        try {
            return \Imagick::queryFormats('WEBP') !== [];
        } catch (\Throwable) {
            return false;
        }
    }

    private function gdAvailable(): bool
    {
        return function_exists('imagecreatefromstring') && function_exists('imagewebp');
    }

    /**
     * @return array{contents: string, width: int, height: int, file_size: int, sha256: string}
     */
    private function withImagick(string $contents): array
    {
        $image = new \Imagick;

        try {
            $image->readImageBlob($contents);
            $image->setIteratorIndex(0);

            if (method_exists($image, 'autoOrient')) {
                $image->autoOrient();
            } elseif (method_exists($image, 'autoOrientImage')) {
                $image->autoOrientImage();
            }

            $this->resizeImagick($image);
            $image->setImagePage(0, 0, 0, 0);
            $image->stripImage();
            $image->setImageFormat('webp');
            $image->setOption('webp:method', '6');
            $image->setImageCompressionQuality($this->quality());
            $webp = $image->getImageBlob();

            if ($webp === '') {
                throw new RuntimeException('ImageMagick could not encode the gallery photo as WebP.');
            }

            return $this->result($webp, $image->getImageWidth(), $image->getImageHeight());
        } finally {
            $image->clear();
            $image->destroy();
        }
    }

    private function resizeImagick(\Imagick $image): void
    {
        $maximumWidth = max(1, (int) config('gallery.max_width', 2400));
        $maximumHeight = max(1, (int) config('gallery.max_height', 2400));

        if ($image->getImageWidth() > $maximumWidth || $image->getImageHeight() > $maximumHeight) {
            $image->thumbnailImage($maximumWidth, $maximumHeight, true, true);
        }
    }

    /**
     * @return array{contents: string, width: int, height: int, file_size: int, sha256: string}
     */
    private function withGd(string $contents): array
    {
        $details = $this->details($contents);
        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            throw new InvalidArgumentException('PHP GD could not read the uploaded image.');
        }

        try {
            if ($details['mime_type'] === 'image/jpeg') {
                $image = $this->orientGdJpeg($image, $contents);
            }

            $image = $this->resizeGd($image);
            imagepalettetotruecolor($image);
            imagealphablending($image, false);
            imagesavealpha($image, true);

            ob_start();
            $encoded = imagewebp($image, null, $this->quality());
            $webp = ob_get_clean();

            if (! $encoded || ! is_string($webp) || $webp === '') {
                throw new RuntimeException('PHP GD could not encode the gallery photo as WebP.');
            }

            return $this->result($webp, imagesx($image), imagesy($image));
        } finally {
            imagedestroy($image);
        }
    }

    private function orientGdJpeg(\GdImage $image, string $contents): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'gallery-orientation-');
        if ($temporaryPath === false) {
            return $image;
        }
        if (file_put_contents($temporaryPath, $contents) === false) {
            @unlink($temporaryPath);

            return $image;
        }

        try {
            $exif = @exif_read_data($temporaryPath);
            $orientation = (int) ($exif['Orientation'] ?? 1);
        } finally {
            @unlink($temporaryPath);
        }

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => false,
        };

        if ($rotated === false) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }

    private function resizeGd(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(
            1,
            max(1, (int) config('gallery.max_width', 2400)) / $width,
            max(1, (int) config('gallery.max_height', 2400)) / $height,
        );

        if ($scale >= 1) {
            return $image;
        }

        $targetWidth = max(1, (int) floor($width * $scale));
        $targetHeight = max(1, (int) floor($height * $scale));
        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefilledrectangle($resized, 0, 0, $targetWidth, $targetHeight, $transparent);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($image);

        return $resized;
    }

    private function quality(): int
    {
        return max(0, min(100, (int) config('gallery.webp_quality', 82)));
    }

    /**
     * @return array{contents: string, width: int, height: int, file_size: int, sha256: string}
     */
    private function result(string $contents, int $width, int $height): array
    {
        return [
            'contents' => $contents,
            'width' => $width,
            'height' => $height,
            'file_size' => strlen($contents),
            'sha256' => hash('sha256', $contents),
        ];
    }
}
