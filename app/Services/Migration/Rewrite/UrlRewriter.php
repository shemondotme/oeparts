<?php

namespace App\Services\Migration\Rewrite;

/**
 * Replaces the address of one site with another inside text that is stored raw: page bodies, menu links, JSON
 * snapshots, CSS. Pure: it touches no database and no file, so it can be proven on its own.
 *
 * It rewrites an address only where it is unmistakably an address of the old site:
 *
 *   https://old.example/p     plain, http or https, with or without www. and with or without a port
 *   https:\/\/old.example\/p  as JSON stores it (escaped slashes)
 *   https%3A%2F%2Fold.example as it appears inside a query string
 *   //old.example/p           protocol-relative
 *
 * and it leaves alone everything that only looks similar: a longer host that merely starts with the old one
 * (old.example.evil.com, old.example-shop.com), an email address (info@old.example), a bare mention with no
 * scheme (old.example in running text), another site's address that has the old path in it. The scheme of the new
 * address is used everywhere, so an http copy moved to https upgrades its links. Rewriting twice changes nothing.
 *
 * Out of scope on purpose: a site moved into a sub-directory (the path would have to change too), and addresses
 * written as HTML entities (&#x2F;). Both are reported by the leak scanner instead of being guessed at.
 */
final class UrlRewriter
{
    private string $newScheme;

    private string $newAuthority;

    private string $hostPattern;

    /** @var array<int, string> */
    private array $fromHosts;

    /**
     * @param  array<int, string>|string  $from  the old address(es): a host, a host:port or a full URL; several for a site that was reached under more than one name
     * @param  string  $to  the new address as a full URL: https://new.example or http://new.example:8080
     */
    public function __construct(array|string $from, string $to)
    {
        $target = parse_url(trim($to));

        if (! is_array($target) || ! in_array(strtolower((string) ($target['scheme'] ?? '')), ['http', 'https'], true) || ($target['host'] ?? '') === '') {
            throw new \InvalidArgumentException('The new address must be a full http:// or https:// URL, for example https://shop.example.');
        }

        $this->newScheme = strtolower((string) $target['scheme']);
        $this->newAuthority = strtolower((string) $target['host']).(isset($target['port']) ? ':'.$target['port'] : '');

        $hosts = [];

        foreach ((array) $from as $address) {
            $normal = self::normaliseFrom((string) $address);

            if ($normal !== '' && ! in_array($normal, $hosts, true)) {
                $hosts[] = $normal;
            }
        }

        if ($hosts === []) {
            throw new \InvalidArgumentException('There is no old address to look for.');
        }

        // Longest first, so "shop.example:8080" is tried before "shop.example".
        usort($hosts, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $this->fromHosts = $hosts;
        $this->hostPattern = $this->buildHostPattern($hosts);
    }

    /** @return array<int, string> the old hosts being looked for, as normalised (lowercase, no scheme, no www.) */
    public function fromHosts(): array
    {
        return $this->fromHosts;
    }

    public function toAuthority(): string
    {
        return $this->newAuthority;
    }

    public function toScheme(): string
    {
        return $this->newScheme;
    }

    /** The shortest text every address of the old site contains: a database LIKE prefilter. */
    public function prefilterNeedles(): array
    {
        return array_map(static fn (string $host): string => explode(':', $host)[0], $this->fromHosts);
    }

    /** Does this text hold at least one address of the old site that would be rewritten? */
    public function wouldChange(string $text): bool
    {
        return $this->rewrite($text) !== $text;
    }

    /**
     * @param  int  $count  set to how many addresses were rewritten
     */
    public function rewrite(string $text, int &$count = 0): string
    {
        $count = 0;
        $needles = $this->prefilterNeedles();

        // Cheap exit: most text never mentions the old host at all.
        $mentions = false;
        foreach ($needles as $needle) {
            if (stripos($text, $needle) !== false) {
                $mentions = true;
                break;
            }
        }

        if (! $mentions) {
            return $text;
        }

        $absolute = '~(?<![A-Za-z0-9+.\-])(?<scheme>https?)(?<sep>:\\\\?/\\\\?/|%3A%2F%2F)(?<host>'.$this->hostPattern.')~i';
        $text = $this->replaceOrFail($absolute, function (array $m) use (&$count): string {
            $count++;

            return $this->newScheme.$m['sep'].$this->authorityFor($m['sep']);
        }, $text);

        $relative = '~(?<![A-Za-z0-9:/\\\\.\-_\~%@])(?<sep>//|\\\\/\\\\/)(?<host>'.$this->hostPattern.')~i';

        return $this->replaceOrFail($relative, function (array $m) use (&$count): string {
            $count++;

            return $m['sep'].$this->authorityFor($m['sep']);
        }, $text);
    }

    /**
     * A regular expression that fails (a backtracking or memory limit on a huge value) returns null, and casting that
     * to a string would silently turn the stored text into an empty string. It must stop the run instead.
     *
     * @param  callable(array<int|string, string>): string  $callback
     */
    private function replaceOrFail(string $pattern, callable $callback, string $text): string
    {
        $result = preg_replace_callback($pattern, $callback, $text);

        if ($result === null) {
            throw new \RuntimeException('The address rewrite could not process a value ('.preg_last_error_msg().'). Nothing was changed.');
        }

        return $result;
    }

    /**
     * Rewrite JSON text and prove the result is still the same JSON with only string values changed.
     */
    public function rewriteJson(string $raw): RewriteOutcome
    {
        if (! json_validate($raw)) {
            // Not JSON to begin with: nothing to protect, treat it as plain text.
            $count = 0;
            $changed = $this->rewrite($raw, $count);

            return new RewriteOutcome($changed, $count, false);
        }

        $count = 0;
        $changed = $this->rewrite($raw, $count);

        if ($changed === $raw) {
            return new RewriteOutcome($raw, 0, false);
        }

        if (! json_validate($changed) || ! self::sameShape(json_decode($raw, true), json_decode($changed, true))) {
            return new RewriteOutcome($raw, 0, true);
        }

        return new RewriteOutcome($changed, $count, false);
    }

    // ---------------------------------------------------------------------------------------------------

    private static function normaliseFrom(string $address): string
    {
        $address = trim($address);

        if ($address === '') {
            return '';
        }

        $parsed = parse_url(str_contains($address, '://') ? $address : '//'.$address);
        $host = strtolower((string) ($parsed['host'] ?? ''));

        if ($host === '') {
            return '';
        }

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host.(isset($parsed['port']) ? ':'.$parsed['port'] : '');
    }

    /** @param  array<int, string>  $hosts */
    private function buildHostPattern(array $hosts): string
    {
        $alternatives = [];

        foreach ($hosts as $host) {
            [$name, $port] = array_pad(explode(':', $host, 2), 2, null);
            $quoted = preg_quote($name, '~');

            // A host written with a port matches only that port. A host written without one matches with any port
            // (or none): the port belonged to the old site and goes with it.
            $portPart = $port !== null ? ':'.preg_quote($port, '~').'(?![0-9])' : '(?::[0-9]{1,5}(?![0-9]))?';
            $alternatives[] = '(?:www\.)?'.$quoted.$portPart;
        }

        // The host must END here: not the start of a longer name (old.example.evil.com, old.example-shop.com),
        // while a full stop that ends a sentence is fine.
        // "@" after the host means it was the user-info part of somebody else's address; a colon and digits left over
        // mean a port that is not the old site's.
        return '(?:'.implode('|', $alternatives).')(?![A-Za-z0-9_\-@])(?!\.[A-Za-z0-9])(?!:[0-9])';
    }

    /** The new authority, written the way the separator that introduced it escapes colons. */
    private function authorityFor(string $separator): string
    {
        return stripos($separator, '%3A') !== false ? str_replace(':', '%3A', $this->newAuthority) : $this->newAuthority;
    }

    /** Same keys, same nesting, same scalar types: only the text of strings may differ. */
    private static function sameShape(mixed $a, mixed $b): bool
    {
        if (is_array($a) || is_array($b)) {
            if (! is_array($a) || ! is_array($b) || array_keys($a) !== array_keys($b)) {
                return false;
            }

            foreach ($a as $key => $value) {
                if (! self::sameShape($value, $b[$key])) {
                    return false;
                }
            }

            return true;
        }

        return gettype($a) === gettype($b) && (! is_string($a) || is_string($b));
    }
}
