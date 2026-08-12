<?php

namespace App\Http\Controllers;

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
        $lines = ['User-agent: *'];

        if (app()->environment('production')) {
            // Admin and account areas — never crawl
            $lines[] = 'Disallow: /admin/';
            $lines[] = 'Disallow: /*/account/';
            $lines[] = 'Disallow: /*/checkout/';
            $lines[] = 'Disallow: /*/cart/';
            $lines[] = 'Disallow: /*/reset-password/';
            $lines[] = 'Disallow: /install/';

            // Faceted / filter URLs — prevent duplicate-content crawling
            $lines[] = 'Disallow: /*?sort=';
            $lines[] = 'Disallow: /*?condition=';
            $lines[] = 'Disallow: /*?manufacturer=';
            $lines[] = 'Disallow: /*?page=';
            $lines[] = 'Disallow: /*?in_stock=';
            $lines[] = 'Disallow: /*?model=';

            $lines[] = 'Allow: /';

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
