<?php

namespace App\Services;

use App\Models\Redirect;

/**
 * Wildcard and regular-expression redirect rules.
 *
 *  - wildcard: "*" in from_url matches any text (including "/"); "$1", "$2"… in the destination stand for
 *    the 1st, 2nd… "*". Example: from "en/old-shop/*"  to "/en/parts/$1".
 *  - regex: from_url is a PCRE pattern written WITHOUT delimiters or anchors (the whole path must match,
 *    case-insensitively); "$1"… in the destination are its capture groups.
 *
 * The path matched against is the request path without a leading slash, e.g. "en/old-shop/brakes".
 */
final class RedirectPattern
{
    /**
     * The delimited, anchored PCRE for a rule, or null when it cannot be compiled.
     */
    public static function compile(string $matchType, string $from): ?string
    {
        $from = trim($from);
        if ($from === '') {
            return null;
        }

        if ($matchType === Redirect::MATCH_WILDCARD) {
            $parts = array_map(fn (string $p) => preg_quote($p, '#'), explode('*', strtolower(trim($from, '/'))));
            $regex = '#^'.implode('(.*)', $parts).'$#i';
        } elseif ($matchType === Redirect::MATCH_REGEX) {
            $body = preg_replace('/^\^|\$$/', '', $from) ?? $from;
            $regex = '#^(?:'.str_replace('#', '\#', ltrim($body, '/')).')$#iu';
        } else {
            return null;
        }

        // A pattern that does not compile must never reach a request: preg_match() returns false for it.
        return @preg_match($regex, '') === false ? null : $regex;
    }

    /**
     * Applies a rule to a path. Returns the destination (with $n filled in, "" for a 410 rule) or null when
     * the rule does not match. Never throws.
     */
    public static function apply(Redirect $rule, string $path): ?string
    {
        $regex = self::compile((string) $rule->match_type, (string) $rule->from_url);
        if ($regex === null) {
            return null;
        }

        $matched = @preg_match($regex, ltrim($path, '/'), $m);
        if ($matched !== 1) {
            return null; // 0 = no match, false = engine error (e.g. backtrack limit): skip the rule
        }

        return self::fill((string) $rule->to_url, $m);
    }

    /**
     * Would this rule send the visitor to a URL that the SAME rule matches again (an endless redirect)?
     * Tested with a stand-in capture value, at save time.
     */
    public static function redirectsToItself(string $matchType, string $from, string $to): bool
    {
        $regex = self::compile($matchType, $from);
        if ($regex === null || $to === '') {
            return false;
        }

        foreach (['x', 'abc/def', '1'] as $sample) {
            $captures = array_fill(0, 10, $sample);
            $target = self::fill($to, $captures);
            $path = strtolower(ltrim((string) (parse_url($target, PHP_URL_PATH) ?? $target), '/'));

            if (@preg_match($regex, $path) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Substitutes $1..$9 with the captured text. Captured text comes from the visitor's URL, so control
     * characters are stripped (no header injection) and a leading "//" is collapsed (no turning a captured
     * value into a protocol-relative link to another site).
     *
     * @param  array<int, string>  $captures  index 0 = whole match
     */
    private static function fill(string $to, array $captures): string
    {
        $out = preg_replace_callback('/\$(\d)/', function (array $m) use ($captures): string {
            return preg_replace('/[\x00-\x1F\x7F]/', '', (string) ($captures[(int) $m[1]] ?? '')) ?? '';
        }, $to) ?? $to;

        return preg_replace('#^/{2,}#', '/', $out) ?? $out;
    }
}
