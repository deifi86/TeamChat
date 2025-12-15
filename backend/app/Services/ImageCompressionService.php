<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Tinify\Tinify;

class ImageCompressionService
{
    protected ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.tinypng.api_key');

        if ($this->apiKey) {
            Tinify::setKey($this->apiKey);
        }
    }

    /**
     * Compress an image using TinyPNG if API key is available,
     * otherwise use Intervention Image
     *
     * @param string $sourcePath - Full path to source image
     * @param string $destinationPath - Full path for compressed image
     * @param int $quality - Quality for Intervention Image (0-100)
     * @return array ['success' => bool, 'original_size' => int, 'compressed_size' => int, 'message' => string]
     */
    public function compress(string $sourcePath, string $destinationPath, int $quality = 80): array
    {
        if (!file_exists($sourcePath)) {
            return [
                'success' => false,
                'original_size' => 0,
                'compressed_size' => 0,
                'message' => 'Source file does not exist',
            ];
        }

        $originalSize = filesize($sourcePath);

        try {
            if ($this->apiKey) {
                return $this->compressWithTinyPNG($sourcePath, $destinationPath, $originalSize);
            } else {
                return $this->compressWithIntervention($sourcePath, $destinationPath, $quality, $originalSize);
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'original_size' => $originalSize,
                'compressed_size' => 0,
                'message' => 'Compression failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Compress using TinyPNG API
     */
    protected function compressWithTinyPNG(string $sourcePath, string $destinationPath, int $originalSize): array
    {
        $source = Tinify::fromFile($sourcePath);
        $source->toFile($destinationPath);

        $compressedSize = filesize($destinationPath);

        return [
            'success' => true,
            'original_size' => $originalSize,
            'compressed_size' => $compressedSize,
            'message' => 'Compressed with TinyPNG',
            'savings' => round((($originalSize - $compressedSize) / $originalSize) * 100, 2) . '%',
        ];
    }

    /**
     * Compress using Intervention Image
     */
    protected function compressWithIntervention(string $sourcePath, string $destinationPath, int $quality, int $originalSize): array
    {
        $image = Image::read($sourcePath);

        // Resize if image is too large (max 2000px on longest side)
        if ($image->width() > 2000 || $image->height() > 2000) {
            $image->scale(width: 2000, height: 2000);
        }

        // Save with compression
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

        if ($extension === 'jpg' || $extension === 'jpeg') {
            $image->toJpeg($quality)->save($destinationPath);
        } elseif ($extension === 'png') {
            $image->toPng()->save($destinationPath);
        } elseif ($extension === 'webp') {
            $image->toWebp($quality)->save($destinationPath);
        } else {
            $image->save($destinationPath);
        }

        $compressedSize = filesize($destinationPath);

        return [
            'success' => true,
            'original_size' => $originalSize,
            'compressed_size' => $compressedSize,
            'message' => 'Compressed with Intervention Image',
            'savings' => round((($originalSize - $compressedSize) / $originalSize) * 100, 2) . '%',
        ];
    }

    /**
     * Check if TinyPNG API is available
     */
    public function hasTinyPNG(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Get TinyPNG compression count for current month
     */
    public function getCompressionCount(): ?int
    {
        if (!$this->apiKey) {
            return null;
        }

        try {
            Tinify::validate();
            return Tinify::compressionCount();
        } catch (\Exception $e) {
            return null;
        }
    }
}
