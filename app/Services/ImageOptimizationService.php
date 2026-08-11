<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Runs after UploadedImageSanitizer on every media-library-family upload
 * (Media Library, the inline WYSIWYG editor image tool, the Media Picker) —
 * NOT on refund evidence photos (AccountController::storeRefundImage),
 * which must stay byte-for-byte as the customer submitted them.
 *
 * Every uploaded image was stored exactly as received: no size cap (a 6000px,
 * 8 MB phone photo used as a manufacturer logo ships at full resolution to
 * every visitor), no re-compression, and no WebP conversion despite the app
 * already re-encoding every image through GD anyway (UploadedImageSanitizer).
 *
 * Pure GD (already a hard dependency via UploadedImageSanitizer — no new
 * composer package). Every step is opt-in via settings and fails soft: an
 * image GD can't decode, or any error mid-pass, leaves the original file
 * untouched rather than breaking the upload.
 */
class ImageOptimizationService
{
    /**
     * @return array{path: string, mime: string, size: int|null} The final
     *         stored path/mime/size — may differ from the input if WebP
     *         conversion changed the file extension/format.
     */
    public function optimize(string $disk, string $path, ?string $mime): array
    {
        $unchanged = ['path' => $path, 'mime' => $mime ?? 'application/octet-stream', 'size' => Storage::disk($disk)->size($path) ?: null];

        // Booleans an admin toggles via the Performance settings page save as
        // the literal string 'false' (SettingsPage::persistChanges()) — a
        // naive truthiness check never disables anything, since any
        // non-empty string (including 'false') is PHP-truthy.
        if (! filter_var(settings('performance.optimize_images', true), FILTER_VALIDATE_BOOLEAN)) {
            return $unchanged;
        }

        $store = Storage::disk($disk);
        $absolutePath = $store->path($path);

        $image = $this->load($absolutePath, $mime);
        if ($image === null) {
            return $unchanged;
        }

        try {
            $image = $this->resizeIfOversized($image);
            $quality = max(1, min(100, (int) settings('performance.image_quality', 82)));

            // GIFs may be animated — GD's GIF functions only ever read/write
            // a single frame, so both resizing and WebP-converting a GIF
            // would silently flatten an animation to its first frame. Left
            // as-is entirely (matches UploadedImageSanitizer's own GIF
            // handling, which re-encodes but never resizes).
            if ($mime === 'image/gif') {
                imagedestroy($image);

                return $unchanged;
            }

            $convertToWebp = filter_var(settings('performance.image_convert_webp', true), FILTER_VALIDATE_BOOLEAN)
                && function_exists('imagewebp');

            if ($convertToWebp && $mime !== 'image/webp') {
                return $this->saveAsWebp($image, $store, $path, $quality);
            }

            $this->saveInPlace($image, $absolutePath, $mime, $quality);
            imagedestroy($image);

            return ['path' => $path, 'mime' => $mime, 'size' => $store->size($path) ?: null];
        } catch (\Throwable) {
            // Optimization failing must never break the upload — the
            // already-sanitized original (or whatever partial state $image
            // left the destination in) is still a valid, safe file.
            return $unchanged;
        }
    }

    /** @return \GdImage|null */
    private function load(string $absolutePath, ?string $mime)
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($absolutePath) ?: null,
            'image/png' => @imagecreatefrompng($absolutePath) ?: null,
            'image/webp' => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($absolutePath) ?: null) : null,
            default => null,
        };
    }

    /**
     * Downscales (never upscales) to fit within the configured max
     * dimensions, preserving aspect ratio. A cap, not a target — an image
     * already smaller than the limit is returned untouched.
     *
     * @param  \GdImage  $image
     * @return \GdImage
     */
    private function resizeIfOversized($image)
    {
        $maxWidth = max(1, (int) settings('performance.image_max_width', 2000));
        $maxHeight = max(1, (int) settings('performance.image_max_height', 2000));

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= $maxWidth && $height <= $maxHeight) {
            return $image;
        }

        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        return $resized;
    }

    private function saveInPlace($image, string $absolutePath, string $mime, int $quality): void
    {
        if ($mime === 'image/jpeg') {
            imagejpeg($image, $absolutePath, $quality);
        } elseif ($mime === 'image/png') {
            imagesavealpha($image, true);
            // GD's PNG "quality" is compression level 0 (none) - 9 (max),
            // inverted from JPEG/WebP's 0-100 meaning — map the same
            // settings value onto PNG's own scale rather than exposing a
            // second, differently-scaled setting.
            imagepng($image, $absolutePath, (int) round((100 - $quality) / 100 * 9));
        } elseif ($mime === 'image/webp') {
            imagewebp($image, $absolutePath, $quality);
        }
    }

    /** @return array{path: string, mime: string, size: int|null} */
    private function saveAsWebp($image, $store, string $originalPath, int $quality): array
    {
        $webpPath = preg_replace('/\.[^.\/]+$/', '.webp', $originalPath);
        // No extension to replace (unlikely, upload validation requires one)
        // — fall back to appending rather than silently colliding with the original.
        if ($webpPath === $originalPath) {
            $webpPath = $originalPath . '.webp';
        }

        $absoluteWebpPath = $store->path($webpPath);
        imagealphablending($image, true);
        imagesavealpha($image, true);
        $ok = imagewebp($image, $absoluteWebpPath, $quality);
        imagedestroy($image);

        if (! $ok || ! $store->exists($webpPath)) {
            return ['path' => $originalPath, 'mime' => $store->mimeType($originalPath) ?: 'application/octet-stream', 'size' => $store->size($originalPath) ?: null];
        }

        if ($webpPath !== $originalPath) {
            $store->delete($originalPath);
        }

        return ['path' => $webpPath, 'mime' => 'image/webp', 'size' => $store->size($webpPath) ?: null];
    }
}
