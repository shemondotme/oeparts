<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Downloads a product image from an admin-supplied external URL — used when
 * a product is scraped from another site and only its image *link* was
 * captured, not the file itself. Once stored, the result is
 * indistinguishable from a direct FileUpload: ProductImageObserver picks it
 * up and dispatches ProcessProductImage (thumbnail/medium generation)
 * exactly the same way.
 *
 * Fetching an admin-supplied URL server-side is an SSRF vector, so the
 * target host is resolved and rejected if it points at a private/loopback/
 * link-local/reserved address before any request is made, and redirects are
 * refused outright rather than followed — a redirect could otherwise
 * retarget the second hop at an internal address after the first host
 * passed the check.
 */
class RemoteImageDownloadService
{
    private const ALLOWED_MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    /** Matches the direct-upload FileUpload's own ->maxSize(4096) cap. */
    private const MAX_BYTES = 4 * 1024 * 1024;

    public function downloadToProductImages(string $url): string
    {
        $this->assertSafeUrl($url);

        $response = Http::timeout(10)
            ->withOptions(['allow_redirects' => false])
            ->withHeaders(['User-Agent' => 'OeParts-ImageFetcher/1.0'])
            ->get($url);

        $status = $response->status();

        if ($status >= 300 && $status < 400) {
            throw new InvalidArgumentException('That URL redirects to another address — use the final, direct image URL instead.');
        }

        if (! $response->successful()) {
            throw new InvalidArgumentException("The URL returned an error (HTTP {$status}).");
        }

        $body = $response->body();

        if ($body === '') {
            throw new InvalidArgumentException('The URL returned an empty response.');
        }

        if (strlen($body) > self::MAX_BYTES) {
            throw new InvalidArgumentException('That image is larger than 4MB.');
        }

        $imageInfo = @getimagesizefromstring($body);
        $mime = $imageInfo['mime'] ?? null;

        if ($mime === null || ! array_key_exists($mime, self::ALLOWED_MIME_EXTENSIONS)) {
            throw new InvalidArgumentException('That URL did not return a valid JPEG, PNG, GIF, or WebP image.');
        }

        app(UploadedImageSanitizer::class)->assertSafeContents($body);

        $path = 'product-images/' . Str::uuid() . '.' . self::ALLOWED_MIME_EXTENSIONS[$mime];

        Storage::disk('public')->put($path, $body);
        app(UploadedImageSanitizer::class)->sanitize('public', $path, $mime);

        return $path;
    }

    private function assertSafeUrl(string $url): void
    {
        $parts = parse_url($url);

        if (! $parts || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true) || empty($parts['host'])) {
            throw new InvalidArgumentException('Enter a valid http:// or https:// image URL.');
        }

        $host = $parts['host'];
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : $this->resolveHost($host);

        if ($ip === $host && ! filter_var($host, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException("Could not resolve that URL's host.");
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new InvalidArgumentException('That URL points at a private or reserved address and cannot be used.');
        }
    }

    /** Isolated for tests — avoids a real DNS round-trip when exercising the happy path against a faked HTTP response. */
    protected function resolveHost(string $host): string
    {
        return gethostbyname($host);
    }
}
