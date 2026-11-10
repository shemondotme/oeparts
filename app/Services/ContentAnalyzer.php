<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * On-page analysis of one piece of written content for one language: is the
 * focus keyword where search engines and readers look for it, and is the
 * text long enough, structured and readable?
 *
 * Deliberately language-neutral. Keyword matching ignores case and accents
 * ("Bremsscheibe" = "bremsscheibe", "Škoda" = "skoda"), and the readability
 * checks use only sentence and paragraph length — no per-language syllable
 * formulas that would be wrong for Lithuanian or Spanish. Advisory only: it
 * never blocks saving.
 */
final class ContentAnalyzer
{
    public const GOOD = 'good';

    public const WARN = 'warn';

    public const BAD = 'bad';

    /**
     * @param  mixed  $content  the rich-editor state: HTML string, or a Tiptap document (array)
     * @return array{score: int, label: string, checks: list<array{id: string, status: string, message: string}>}
     */
    public function analyze(?string $focusKeyword, ?string $metaTitle, ?string $metaDescription, ?string $slug, mixed $content, ?string $title = null): array
    {
        $html = $this->toHtml($content);
        $text = $this->plainText($html);
        $words = $this->words($text);
        $wordCount = count($words);
        $keyword = $this->norm((string) $focusKeyword);
        $checks = [];

        // ── Focus keyword ─────────────────────────────────────────────
        if ($keyword === '') {
            $checks[] = $this->check('keyword_set', self::BAD, 'No focus keyword yet. Choose the phrase this page should be found for — the keyword checks below start working once you do.');
        } else {
            $checks[] = $this->check('keyword_set', self::GOOD, 'Focus keyword: "'.trim((string) $focusKeyword).'".');

            $searchTitle = $this->norm((string) ($metaTitle ?: $title));
            $checks[] = match (true) {
                $searchTitle === '' => $this->check('keyword_title', self::BAD, 'There is no search title to look in.'),
                str_starts_with($searchTitle, $keyword) => $this->check('keyword_title', self::GOOD, 'The search title starts with the keyword.'),
                str_contains($searchTitle, $keyword) => $this->check('keyword_title', self::GOOD, 'The keyword is in the search title. Starting with it is slightly stronger.'),
                default => $this->check('keyword_title', self::BAD, 'The keyword is not in the search title.'),
            };

            $description = $this->norm((string) $metaDescription);
            $checks[] = match (true) {
                $description === '' => $this->check('keyword_description', self::WARN, 'There is no search description — Google will pick the text itself.'),
                str_contains($description, $keyword) => $this->check('keyword_description', self::GOOD, 'The keyword is in the search description.'),
                default => $this->check('keyword_description', self::WARN, 'The keyword is not in the search description.'),
            };

            $slugNorm = str_replace('-', ' ', $this->norm((string) $slug));
            $checks[] = ($slugNorm !== '' && str_contains($slugNorm, str_replace('-', ' ', Str::slug($keyword))))
                ? $this->check('keyword_slug', self::GOOD, 'The keyword is in the address (URL).')
                : $this->check('keyword_slug', self::WARN, 'The keyword is not in the address (URL).');

            $firstParagraph = $this->norm($this->firstParagraph($html));
            $firstWords = $this->norm(implode(' ', array_slice($words, 0, 100)));
            $checks[] = (str_contains($firstParagraph, $keyword) || str_contains($firstWords, $keyword))
                ? $this->check('keyword_intro', self::GOOD, 'The keyword appears in the opening.')
                : $this->check('keyword_intro', self::WARN, 'The keyword does not appear in the first paragraph.');

            $headings = $this->headings($html);
            if ($headings === []) {
                $checks[] = $this->check('keyword_heading', self::WARN, 'There are no subheadings (H2/H3) to put the keyword in.');
            } else {
                $inHeading = array_filter($headings, fn (string $h) => str_contains($this->norm($h), $keyword));
                $checks[] = $inHeading !== []
                    ? $this->check('keyword_heading', self::GOOD, 'The keyword is in a subheading.')
                    : $this->check('keyword_heading', self::WARN, 'The keyword is in none of the subheadings.');
            }

            if ($wordCount >= 50) {
                $count = substr_count($this->norm($text), $keyword);
                $density = round(100 * $count * max(1, count(explode(' ', $keyword))) / $wordCount, 1);
                $checks[] = match (true) {
                    $count === 0 => $this->check('keyword_density', self::BAD, 'The keyword does not appear in the text.'),
                    $density > 4 => $this->check('keyword_density', self::BAD, "The keyword appears {$count}× ({$density}% of the words) — that reads as keyword stuffing."),
                    $density > 3 => $this->check('keyword_density', self::WARN, "The keyword appears {$count}× ({$density}%) — a little often."),
                    $density < 0.5 => $this->check('keyword_density', self::WARN, "The keyword appears only {$count}× ({$density}%). Use it a few more times where it reads naturally."),
                    default => $this->check('keyword_density', self::GOOD, "The keyword appears {$count}× ({$density}% of the words) — a natural amount."),
                };
            }

            $images = $this->imageAlts($html);
            if ($images !== []) {
                $withKeyword = array_filter($images, fn (string $alt) => str_contains($this->norm($alt), $keyword));
                $checks[] = $withKeyword !== []
                    ? $this->check('keyword_image', self::GOOD, 'An image description (alt text) contains the keyword.')
                    : $this->check('keyword_image', self::WARN, 'No image description (alt text) contains the keyword.');
            }
        }

        // ── Content ───────────────────────────────────────────────────
        $checks[] = match (true) {
            $wordCount >= 300 => $this->check('length', self::GOOD, "The text is {$wordCount} words long."),
            $wordCount >= 150 => $this->check('length', self::WARN, "The text is {$wordCount} words — fine for a short page, but 300+ words usually ranks better."),
            default => $this->check('length', self::BAD, "The text is only {$wordCount} words — very thin for a search engine."),
        };

        $links = $this->linkHrefs($html);
        $internal = array_filter($links, fn (string $h) => ! preg_match('#^(https?:)?//#i', $h) && ! preg_match('/^(mailto|tel):/i', $h) && $h !== '' && $h[0] !== '#');
        $checks[] = $internal !== []
            ? $this->check('internal_links', self::GOOD, 'The text links to other pages of this site.')
            : $this->check('internal_links', self::WARN, 'The text links to no other page of this site. Linking related pages helps visitors and search engines.');

        if ($wordCount > 300) {
            $checks[] = $this->headings($html) !== []
                ? $this->check('structure', self::GOOD, 'The text is broken up with subheadings.')
                : $this->check('structure', self::WARN, 'A long text without subheadings is hard to scan. Add H2/H3 headings.');
        }

        // ── Readability (sentence and paragraph length only) ──────────
        if ($wordCount >= 50) {
            $sentences = $this->sentences($text);
            $average = $sentences === [] ? 0 : round($wordCount / count($sentences), 1);
            $checks[] = match (true) {
                $average > 25 => $this->check('sentences', self::BAD, "Sentences average {$average} words — too long to read comfortably (aim for under 20)."),
                $average > 20 => $this->check('sentences', self::WARN, "Sentences average {$average} words. Shorter sentences are easier to read."),
                default => $this->check('sentences', self::GOOD, "Sentences average {$average} words — easy to read."),
            };

            $long = count(array_filter($this->paragraphs($html), fn (string $p) => count($this->words($p)) > 150));
            if ($long > 0) {
                $checks[] = $this->check('paragraphs', self::WARN, $long === 1 ? 'One paragraph is over 150 words. Split it up.' : "{$long} paragraphs are over 150 words. Split them up.");
            }
        }

        return $this->summarise($checks);
    }

    // ── Scoring ───────────────────────────────────────────────────────────

    /**
     * @param  list<array{id: string, status: string, message: string}>  $checks
     * @return array{score: int, label: string, checks: list<array{id: string, status: string, message: string}>}
     */
    private function summarise(array $checks): array
    {
        $points = array_sum(array_map(fn (array $c) => match ($c['status']) {
            self::GOOD => 1.0,
            self::WARN => 0.5,
            default => 0.0,
        }, $checks));

        $score = $checks === [] ? 0 : (int) round(100 * $points / count($checks));

        return [
            'score' => $score,
            'label' => match (true) {
                $score >= 75 => 'Good',
                $score >= 45 => 'Needs improvement',
                default => 'Needs work',
            },
            'checks' => $checks,
        ];
    }

    /** @return array{id: string, status: string, message: string} */
    private function check(string $id, string $status, string $message): array
    {
        return ['id' => $id, 'status' => $status, 'message' => $message];
    }

    // ── Text helpers ──────────────────────────────────────────────────────

    /** Lower case, accents removed, whitespace collapsed — so "Škoda  Bremse" matches "skoda bremse". */
    private function norm(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', Str::ascii(mb_strtolower($value))));
    }

    /** @return list<string> */
    private function words(string $text): array
    {
        return preg_split('/[^\p{L}\p{N}\']+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /** @return list<string> */
    private function sentences(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/(?<=[.!?…])\s+/u', trim($text)) ?: []), fn (string $s) => $s !== ''));
    }

    private function plainText(string $html): string
    {
        $withBreaks = preg_replace('#</(p|h[1-6]|li|div|blockquote|br)\s*>|<br\s*/?>#i', "\n", $html) ?? $html;

        return trim((string) preg_replace('/[ \t]+/', ' ', html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5)));
    }

    private function firstParagraph(string $html): string
    {
        if (preg_match('#<p[^>]*>(.*?)</p>#is', $html, $m) === 1) {
            return $this->plainText($m[1]);
        }

        return (string) strtok($this->plainText($html), "\n");
    }

    /** @return list<string> */
    private function paragraphs(string $html): array
    {
        preg_match_all('#<p[^>]*>(.*?)</p>#is', $html, $m);

        return array_map(fn (string $p) => $this->plainText($p), $m[1] ?? []);
    }

    /** @return list<string> text of every H2/H3 */
    private function headings(string $html): array
    {
        preg_match_all('#<h([23])[^>]*>(.*?)</h\1>#is', $html, $m);

        return array_values(array_filter(array_map(fn (string $h) => $this->plainText($h), $m[2] ?? []), fn (string $h) => $h !== ''));
    }

    /** @return list<string> the alt text of every image ('' for one without any) — empty list when there are no images */
    private function imageAlts(string $html): array
    {
        preg_match_all('#<img\b[^>]*>#i', $html, $imgs);
        $alts = [];

        foreach ($imgs[0] ?? [] as $tag) {
            $alts[] = preg_match('#\balt\s*=\s*("([^"]*)"|\'([^\']*)\')#i', $tag, $m) === 1 ? html_entity_decode($m[2] !== '' ? $m[2] : ($m[3] ?? '')) : '';
        }

        return $alts;
    }

    /** @return list<string> */
    private function linkHrefs(string $html): array
    {
        preg_match_all('#<a\s[^>]*?href\s*=\s*(?:"([^"]*)"|\'([^\']*)\')#i', $html, $m, PREG_SET_ORDER);

        return array_map(fn (array $x) => trim(html_entity_decode($x[1] !== '' ? $x[1] : ($x[2] ?? ''))), $m);
    }

    // ── Editor state → HTML ───────────────────────────────────────────────

    /** The rich editor hands over HTML, or (while editing) a Tiptap document. */
    public function toHtml(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        if (is_array($content)) {
            return $this->nodeToHtml(isset($content['type']) ? $content : ['type' => 'doc', 'content' => $content]);
        }

        return '';
    }

    /** @param array<string, mixed> $node */
    private function nodeToHtml(array $node): string
    {
        $type = $node['type'] ?? '';
        $inner = '';
        foreach ((array) ($node['content'] ?? []) as $child) {
            $inner .= is_array($child) ? $this->nodeToHtml($child) : '';
        }

        switch ($type) {
            case 'text':
                $out = e((string) ($node['text'] ?? ''));
                foreach ((array) ($node['marks'] ?? []) as $mark) {
                    if (($mark['type'] ?? '') === 'link' && isset($mark['attrs']['href'])) {
                        $out = '<a href="'.e((string) $mark['attrs']['href']).'">'.$out.'</a>';
                    }
                }

                return $out;
            case 'paragraph':
                return '<p>'.$inner.'</p>';
            case 'heading':
                $level = max(1, min(6, (int) ($node['attrs']['level'] ?? 2)));

                return "<h{$level}>{$inner}</h{$level}>";
            case 'image':
                return '<img src="'.e((string) ($node['attrs']['src'] ?? '')).'" alt="'.e((string) ($node['attrs']['alt'] ?? '')).'">';
            case 'listItem':
                return '<li>'.$inner.'</li>';
            case 'bulletList':
                return '<ul>'.$inner.'</ul>';
            case 'orderedList':
                return '<ol>'.$inner.'</ol>';
            case 'blockquote':
                return '<blockquote>'.$inner.'</blockquote>';
            case 'hardBreak':
                return '<br>';
            default: // doc and anything unknown
                return $inner;
        }
    }
}
