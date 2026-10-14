<?php

namespace App\Services\Migration\Transport;

/**
 * The text a person copies from the source site to the target: `https://<host>/_pkg/<token>#sha256=<hex>&size=<n>&name=<file>`.
 * Everything after # stays in the person's browser or terminal and is never sent to the server; it only tells the receiving
 * side what to expect (an out-of-band checksum, the size, the file name). It never carries the passphrase.
 */
final class ShareString
{
    public static function build(string $baseUrl, string $token, ?string $sha256, int $size, string $name): string
    {
        $fragment = array_filter(['sha256' => $sha256, 'size' => (string) $size, 'name' => $name], static fn (?string $v): bool => $v !== null && $v !== '');

        return rtrim($baseUrl, '/').'/_pkg/'.$token.'#'.http_build_query($fragment, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array{url: string, sha256: string|null, size: int|null, name: string|null}
     */
    public static function parse(string $link): array
    {
        $link = trim($link);
        $hash = strpos($link, '#');
        parse_str($hash === false ? '' : substr($link, $hash + 1), $fragment);

        return [
            'url' => $hash === false ? $link : substr($link, 0, $hash),
            'sha256' => isset($fragment['sha256']) && is_string($fragment['sha256']) ? $fragment['sha256'] : null,
            'size' => isset($fragment['size']) && is_string($fragment['size']) && ctype_digit($fragment['size']) ? (int) $fragment['size'] : null,
            'name' => isset($fragment['name']) && is_string($fragment['name']) ? $fragment['name'] : null,
        ];
    }
}
