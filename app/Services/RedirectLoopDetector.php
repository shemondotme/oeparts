<?php

namespace App\Services;

use App\Models\Redirect;

/**
 * Detects whether following a redirect chain, starting from a proposed
 * from->to pair, would ever loop back on itself — by walking forward
 * through the currently active redirects.
 *
 * RedirectResource's own save-time validation only ever caught a direct
 * reverse pair (A->B and B->A saved together); a longer chain (A->B, B->C,
 * then saving C->A) went undetected. This walks the full chain instead,
 * bounded by MAX_DEPTH so a pathological/corrupted chain can't hang the
 * request that's checking it.
 */
class RedirectLoopDetector
{
    private const MAX_DEPTH = 25;

    /**
     * Would the chain starting at $to (having arrived via $from) ever loop
     * back to $from or to any node already visited in this walk? $ignoreId
     * excludes the record currently being edited, so re-saving an
     * unchanged row doesn't flag itself as a loop.
     *
     * Returns the node the loop closes on (for the validation message), or
     * null if the chain terminates cleanly (or exceeds MAX_DEPTH without
     * closing — a chain that deep is its own operational smell, worth
     * surfacing separately rather than as a hard save-time failure here).
     */
    public function findLoop(string $from, string $to, ?int $ignoreId = null): ?string
    {
        $from = $this->normalize($from);
        $current = $this->normalize($to);

        $visited = [$from => true];

        for ($depth = 0; $depth < self::MAX_DEPTH; $depth++) {
            if ($current === '') {
                return null;
            }

            if (isset($visited[$current])) {
                return $current;
            }

            $visited[$current] = true;

            $next = Redirect::query()
                ->where('is_active', true)
                ->where('from_url', $current)
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->value('to_url');

            if ($next === null) {
                // No active redirect continues the chain from here — a
                // real destination page, not another hop. Clean.
                return null;
            }

            $current = $this->normalize($next);
        }

        return null;
    }

    /**
     * Proactive sweep across every currently active redirect — used by the
     * SEO Health Dashboard's redirect-health widget to surface loop risk
     * that only gets checked reactively at save time otherwise.
     *
     * @return array<int, string> from_url of every redirect whose own chain loops
     */
    public function findAllLoops(): array
    {
        $flagged = [];

        foreach (Redirect::query()->where('is_active', true)->get(['id', 'from_url', 'to_url']) as $redirect) {
            if ($this->findLoop($redirect->from_url, $redirect->to_url, $redirect->id) !== null) {
                $flagged[] = $redirect->from_url;
            }
        }

        return $flagged;
    }

    /**
     * Matches Redirect::booted()'s own save-time normalization (lowercase,
     * no leading/trailing slash) — this must compare the same way
     * HandleRedirects looks redirects up, or the walk would miss real hops.
     */
    private function normalize(string $url): string
    {
        return strtolower(trim($url, '/'));
    }
}
