<?php

namespace App\Services\Instance\Readiness;

/**
 * Looks in a page for the places an old address hides after a move, the ones a visitor never sees and a search engine reads:
 * the canonical link, the alternate-language links, the Open Graph address and picture, and every address inside the structured
 * data (JSON-LD). Pure: it is given the page text and the hosts that must not appear, and says where they do.
 */
final class HostLeakCrawler
{
    /**
     * @param  array<int, string>  $oldHosts  hosts that must not appear (a leading "www." is ignored on both sides)
     * @return array<int, array{where: string, url: string}>
     */
    public function inspect(string $html, array $oldHosts): array
    {
        $old = array_values(array_filter(array_map(fn (string $h): string => $this->bare($h), $oldHosts), static fn (string $h): bool => $h !== ''));

        if ($old === [] || trim($html) === '') {
            return [];
        }

        $found = [];

        foreach ($this->addresses($html) as [$where, $url]) {
            $host = parse_url(str_starts_with($url, '//') ? 'https:'.$url : $url, PHP_URL_HOST);

            if (is_string($host) && in_array($this->bare($host), $old, true)) {
                $found[] = ['where' => $where, 'url' => $url];
            }
        }

        return $found;
    }

    /** @return array<int, array{0: string, 1: string}> */
    private function addresses(string $html): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument;
        $document->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($document);
        $out = [];

        foreach ($xpath->query('//link[@rel="canonical"]/@href') ?: [] as $node) {
            $out[] = ['canonical', trim($node->nodeValue ?? '')];
        }

        foreach ($xpath->query('//link[@rel="alternate"][@hreflang]/@href') ?: [] as $node) {
            $out[] = ['hreflang', trim($node->nodeValue ?? '')];
        }

        foreach (['og:url', 'og:image', 'og:image:url', 'og:image:secure_url'] as $property) {
            foreach ($xpath->query('//meta[@property="'.$property.'"]/@content') ?: [] as $node) {
                $out[] = [$property, trim($node->nodeValue ?? '')];
            }
        }

        foreach ($xpath->query('//script[@type="application/ld+json"]') ?: [] as $script) {
            $data = json_decode((string) $script->textContent, true);

            if (is_array($data)) {
                array_walk_recursive($data, function (mixed $value) use (&$out): void {
                    if (is_string($value) && preg_match('~^(https?:)?//~i', $value) === 1) {
                        $out[] = ['json-ld', $value];
                    }
                });
            }
        }

        return array_values(array_filter($out, static fn (array $a): bool => $a[1] !== ''));
    }

    private function bare(string $host): string
    {
        $host = strtolower(trim($host));

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
