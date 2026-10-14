<?php

namespace App\Services;

use App\Services\Migration\MigrationException;
use App\Services\Migration\Transport\UrlPolicy;
use App\Services\Migration\Transport\ValidatedUrl;
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
 * URL goes through the same UrlPolicy as a package transfer: every address
 * the host resolves to (IPv4 and IPv6) must be public, and the connection is
 * PINNED to the address that was checked, so the name cannot change its mind
 * between the check and the request (DNS rebinding). Redirects are refused
 * outright rather than followed — a redirect could otherwise retarget the
 * second hop at an internal address after the first host passed the check.
 * Plain http is still accepted here (scraped product links often are).
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

    public function __construct(private readonly UrlPolicy $policy) {}

    public function downloadToProductImages(string $url): string
    {
        $target = $this->validated($url);

        $response = Http::timeout(10)
            ->withOptions([
                'allow_redirects' => false,
                'curl' => [CURLOPT_RESOLVE => [$target->resolveEntry()]],
            ])
            ->withHeaders(['User-Agent' => 'OeParts-ImageFetcher/1.0'])
            ->get($target->url);

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

        $path = 'product-images/'.Str::uuid().'.'.self::ALLOWED_MIME_EXTENSIONS[$mime];

        Storage::disk('public')->put($path, $body);
        app(UploadedImageSanitizer::class)->sanitize('public', $path, $mime);

        return $path;
    }

    private function validated(string $url): ValidatedUrl
    {
        try {
            return $this->policy->validate($url, allowHttp: true);
        } catch (MigrationException $e) {
            throw new InvalidArgumentException(match ($e->params['kind'] ?? 'format') {
                'address' => 'That URL points at a private or reserved address and cannot be used.',
                'unresolvable' => "Could not resolve that URL's host.",
                default => 'Enter a valid http:// or https:// image URL.',
            }, 0, $e);
        }
    }
}
