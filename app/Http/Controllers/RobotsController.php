<?php

namespace App\Http\Controllers;

use App\Services\Instance\SafeMode;
use App\Support\RobotsCustomRules;
use Illuminate\Http\Request;

class RobotsController extends Controller
{
    /**
     * Generate dynamic robots.txt content.
     *
     * Production rules per PRD § Module 8 — Global SEO Engine:
     *   Disallow: /admin/, /*?sort=, /*?condition=, /*?manufacturer=, /*?page=
     * Non-production: disallow everything.
     */
    public function index(Request $request)
    {
        // A staging or quarantine copy keeps every crawler out and advertises nothing, whatever APP_ENV says: a copy
        // is deliberately run production-like, so the instance mode (not the environment) decides.
        $copyRules = app(SafeMode::class)->robotsDirective();

        if ($copyRules !== null) {
            return response($copyRules.'
', 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        $lines = ['User-agent: *'];

        if (app()->environment('production')) {
            // Admin and account areas — never crawl
            $lines[] = 'Disallow: /admin/';
            $lines[] = 'Disallow: /*/account/';
            $lines[] = 'Disallow: /*/checkout/';
            $lines[] = 'Disallow: /*/cart/';
            $lines[] = 'Disallow: /*/reset-password/';
            $lines[] = 'Disallow: /install/';

            // A robots.txt rule is a PREFIX match, so '/*/cart/' does not
            // cover the cart page itself ('/en/cart' has no trailing slash)
            // — the '$' variants close that gap.
            $lines[] = 'Disallow: /*/cart$';
            $lines[] = 'Disallow: /*/checkout$';
            $lines[] = 'Disallow: /*/reset-password$';

            // Endpoints that only make sense to a signed-in or interacting
            // visitor — social-login redirects, the autocomplete JSON API,
            // newsletter/coupon actions, signed invoice downloads — and the
            // monitoring endpoints. Nothing here is content; crawling it only
            // burns crawl budget.
            $lines[] = 'Disallow: /*/auth/';
            $lines[] = 'Disallow: /*/search/';
            $lines[] = 'Disallow: /*/newsletter/';
            $lines[] = 'Disallow: /*/coupon/';
            $lines[] = 'Disallow: /*/orders/';
            $lines[] = 'Disallow: /health';
            $lines[] = 'Disallow: /build-version';

            // Internal-search result URLs (blog ?search=) are an unbounded
            // URL space of thin duplicates.
            $lines[] = 'Disallow: /*?search=';
            $lines[] = 'Disallow: /*&search=';

            // Faceted / filter URLs — prevent duplicate-content crawling
            $lines[] = 'Disallow: /*?sort=';
            $lines[] = 'Disallow: /*?condition=';
            $lines[] = 'Disallow: /*?manufacturer=';
            $lines[] = 'Disallow: /*?page=';
            $lines[] = 'Disallow: /*?in_stock=';
            $lines[] = 'Disallow: /*?model=';

            $lines[] = 'Allow: /';

            // Admin-written extra rules (SEO Control Center → Crawlers & AI).
            // Placed directly after the wildcard group and BEFORE the per-bot
            // groups below, so a bare "Disallow:" line belongs to "*" and never
            // silently attaches to whichever AI bot happens to come last.
            foreach (RobotsCustomRules::lines((string) settings('crawlers.robots_custom_rules', '')) as $customLine) {
                $lines[] = $customLine;
            }

            // AI-crawler policy — admin-editable (SEO Control Center),
            // decided ALLOW by default: the business wants AI-answer-engine
            // citation traffic (GPTBot/ChatGPT, PerplexityBot, etc.), not to
            // block it. Only applied in production — staging already blocks
            // every crawler via the wildcard rule above, and a bot matching
            // its OWN explicit User-agent block ignores that wildcard
            // entirely (per the robots.txt spec), so leaving this out of
            // the non-production branch is what keeps staging fully
            // blocked rather than accidentally re-opening it for AI bots.
            foreach ($this->aiBotRules() as $rule) {
                $userAgent = trim((string) ($rule['user_agent'] ?? ''));
                if ($userAgent === '') {
                    continue;
                }

                $lines[] = '';
                $lines[] = "User-agent: {$userAgent}";
                $lines[] = ($rule['action'] ?? 'allow') === 'block' ? 'Disallow: /' : 'Allow: /';
            }
        } else {
            // Staging / development — block all crawlers completely
            $lines[] = 'Disallow: /';
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.url('sitemap.xml');

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            // Reduced from 1h — AI-bot rules are now admin-editable and
            // should take effect reasonably promptly after a save, not
            // linger stale in a CDN/browser cache for up to an hour.
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    /**
     * @return array<int, array{user_agent: string, action: string}>
     */
    private function aiBotRules(): array
    {
        $raw = settings('crawlers.ai_bot_rules', '[]');
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
