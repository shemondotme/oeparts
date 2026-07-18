<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Defends every image upload endpoint (refund evidence photos, media library,
 * CMS/blog editor images) against the classic "polyglot" attack — a file that
 * is a valid JPEG/PNG/GIF/WEBP by header but also carries executable PHP or
 * script bytes in a comment/metadata segment, banking on the file later being
 * served or included somewhere that executes it. Re-encoding through GD only
 * ever reads/writes actual pixel data, so a smuggled payload is discarded —
 * the same pass also strips EXIF/GPS metadata for privacy (see
 * AccountController::storeRefundImage, the original home of this logic).
 */
class UploadedImageSanitizer
{
    private const SUSPICIOUS_SIGNATURES = [
        '<?php', '<?=', '<%', '<script', 'eval(', 'base64_decode(', '#!/',
    ];

    /**
     * Reject a file outright if its raw bytes contain an obvious script
     * signature — a cheap first line of defense before GD ever touches it.
     */
    public function assertSafe(UploadedFile $file): void
    {
        $contents = file_get_contents($file->getRealPath());

        foreach (self::SUSPICIOUS_SIGNATURES as $signature) {
            if ($contents !== false && stripos($contents, $signature) !== false) {
                throw new \InvalidArgumentException('Uploaded file failed a content safety check.');
            }
        }
    }

    /**
     * Re-encode the already-stored file in place via GD. Silently no-ops for
     * unsupported mime types or unreadable images — sanitization failing is
     * never worth failing the whole upload over, since the raw-byte check in
     * assertSafe() is the actual hard gate.
     */
    public function sanitize(string $disk, string $path, ?string $mime): void
    {
        $absolutePath = Storage::disk($disk)->path($path);

        if ($mime === 'image/jpeg' && ($image = @imagecreatefromjpeg($absolutePath))) {
            imagejpeg($image, $absolutePath, 90);
            imagedestroy($image);
        } elseif ($mime === 'image/png' && ($image = @imagecreatefrompng($absolutePath))) {
            imagesavealpha($image, true);
            imagepng($image, $absolutePath);
            imagedestroy($image);
        } elseif ($mime === 'image/gif' && ($image = @imagecreatefromgif($absolutePath))) {
            imagegif($image, $absolutePath);
            imagedestroy($image);
        } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp') && ($image = @imagecreatefromwebp($absolutePath))) {
            imagewebp($image, $absolutePath);
            imagedestroy($image);
        }
    }
}
