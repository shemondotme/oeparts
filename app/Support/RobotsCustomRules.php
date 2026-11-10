<?php

namespace App\Support;

/**
 * The admin-editable "additional robots.txt rules" block. Appended to the
 * wildcard (`User-agent: *`) group, so it can be as simple as a few
 * `Disallow:` lines. It is deliberately NOT a free-text replacement of the
 * whole file: one stray line must not be able to take the site out of
 * search, so only the standard directives are accepted and a bare
 * `Disallow: /` (which blocks everything) is refused.
 */
final class RobotsCustomRules
{
    public const MAX_LINES = 100;

    public const MAX_LINE_LENGTH = 300;

    /** @return string|null a human-readable problem, or null when the text is fine */
    public static function problem(?string $text): ?string
    {
        $lines = self::rawLines($text);

        if (count($lines) > self::MAX_LINES) {
            return 'At most '.self::MAX_LINES.' lines are allowed.';
        }

        foreach ($lines as $number => $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (mb_strlen($trimmed) > self::MAX_LINE_LENGTH) {
                return 'Line '.($number + 1).' is longer than '.self::MAX_LINE_LENGTH.' characters.';
            }

            if (preg_match('/^(user-agent|disallow|allow|crawl-delay|sitemap)\s*:\s*(\S.*)?$/i', $trimmed, $m) !== 1) {
                return 'Line '.($number + 1).' is not a robots.txt rule. Use "Disallow: /path", "Allow: /path", "User-agent: name", "Crawl-delay: seconds" or "Sitemap: https://…" (or start the line with # for a comment).';
            }

            if (strtolower($m[1]) === 'disallow' && preg_match('#^disallow\s*:\s*/\s*$#i', $trimmed) === 1) {
                return 'Line '.($number + 1).' ("Disallow: /") would block the whole site from search engines. To keep out one crawler, add it under AI Crawler Policy; to keep out a section, name its path.';
            }
        }

        return null;
    }

    /**
     * The lines to append to robots.txt: trimmed, comments and blanks
     * dropped, control characters removed. Anything that fails problem()
     * yields no lines at all rather than a half-applied block.
     *
     * @return list<string>
     */
    public static function lines(?string $text): array
    {
        if (self::problem($text) !== null) {
            return [];
        }

        $out = [];
        foreach (self::rawLines($text) as $line) {
            $line = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $line));

            if ($line !== '' && ! str_starts_with($line, '#')) {
                $out[] = $line;
            }
        }

        return $out;
    }

    /** @return list<string> */
    private static function rawLines(?string $text): array
    {
        $text = trim((string) $text);

        return $text === '' ? [] : (preg_split('/\R/u', $text) ?: []);
    }
}
