<?php

namespace App\Services\Instance\Readiness\Checks;

use App\Services\Instance\Readiness\HostLeakCrawler;
use App\Services\Instance\Readiness\ReadinessCheck as C;
use App\Services\Instance\Readiness\ReadinessContext;
use App\Services\Instance\Readiness\ReadinessProbe;
use App\Services\Instance\Readiness\SmokeRequestRunner;
use App\Services\Instance\Readiness\SmokeResult;
use App\Services\Migration\Rewrite\DomainLeakScanner;
use Illuminate\Support\Facades\DB;

/**
 * What a visitor and a search engine get from the site: its pages must stay on its own address, must not name the old one, and
 * must not loop. The site is asked for its own pages (see SmokeRequestRunner); these can only run once it is open.
 */
final class HttpChecks
{
    private const GROUP = 'domain';

    /** @return array<int, ReadinessProbe> */
    public function probes(): array
    {
        return [
            new ReadinessProbe('cross_host_redirects', self::GROUP, $this->crossHostRedirects(...), ReadinessProbe::LIVE),
            new ReadinessProbe('old_host_in_pages', self::GROUP, $this->oldHostInPages(...), ReadinessProbe::LIVE),
            new ReadinessProbe('rewrite_leftovers', self::GROUP, $this->rewriteLeftovers(...), ReadinessProbe::LIVE),
            new ReadinessProbe('sitemap_host', self::GROUP, $this->sitemapHost(...), ReadinessProbe::LIVE),
            new ReadinessProbe('redirect_loops', self::GROUP, $this->redirectLoops(...), ReadinessProbe::LIVE),
        ];
    }

    /** No page sends a visitor to another host, and none fails with a server error. */
    public function crossHostRedirects(ReadinessContext $c): C
    {
        $label = 'The pages stay on this site';
        $problems = [];

        foreach ($this->pages($c) as $path => $result) {
            $foreign = $result->locationHost();

            if ($result->isServerError()) {
                $problems[$path] = 'answered with an error ('.($result->error ?? $result->status).')';
            } elseif ($foreign !== null && $foreign !== $c->appHost()) {
                $problems[$path] = 'sends visitors to another host';
            }
        }

        $partial = $this->partialNote();

        return $problems === []
            ? $c->check('cross_host_redirects', $label, self::GROUP, C::PASS, count($this->pages($c)).' pages were asked for; none leaves the site or fails.'.$partial, ['partial' => $partial !== ''])
            : $c->check('cross_host_redirects', $label, self::GROUP, C::FAIL, count($problems).' page(s) are wrong: '.implode('; ', array_map(fn (string $p, string $why): string => "{$p} {$why}", array_keys($problems), $problems)).'.'.$partial, ['pages' => $problems], 'Check the canonical host and APP_URL; run the domain rewrite again if links still name the old site.');
    }

    /** The places an old address hides (canonical, hreflang, Open Graph, structured data) are free of it. */
    public function oldHostInPages(ReadinessContext $c): C
    {
        $label = 'The pages do not name the old site';
        $old = $c->oldHosts();

        if ($old === []) {
            return $c->check('old_host_in_pages', $label, self::GROUP, C::SKIP, 'This site does not know of an old address to look for.');
        }

        $crawler = new HostLeakCrawler;
        $leaks = [];

        foreach (['/', '/'.$this->locale().'/'] as $path) {
            $page = $this->settle($c, $path);

            foreach ($crawler->inspect($page->body, $old) as $found) {
                $leaks[] = ['page' => $path, 'where' => $found['where']];
            }
        }

        return $leaks === []
            ? $c->check('old_host_in_pages', $label, self::GROUP, C::PASS, 'None of the old addresses appears in the pages\' links, language links, share cards or structured data.')
            : $c->check('old_host_in_pages', $label, self::GROUP, C::FAIL, count($leaks).' place(s) in the pages still name the old site.', ['places' => $leaks], 'Run the domain rewrite again (oeparts:instance:rewrite-domain) and clear the caches.');
    }

    /** Stored text that still names the old site (a scan of the database; a leak is worth a look, not a stop). */
    public function rewriteLeftovers(ReadinessContext $c): C
    {
        $label = 'The stored content does not name the old site';
        $old = $c->oldHosts();

        if ($old === []) {
            return $c->check('rewrite_leftovers', $label, self::GROUP, C::SKIP, 'This site does not know of an old address to look for.');
        }

        $report = (new DomainLeakScanner)->scan($old);

        if ($report->needsAttention()) {
            return $c->check('rewrite_leftovers', $label, self::GROUP, C::WARN, ($report->hasLeaks() ? count($report->ofClass(DomainLeakScanner::LEAK)).' column(s) still hold the old address.' : 'The scan could not finish every column in time.'), ['scan' => $report->toArray()['counts']], 'Look at the scan (oeparts:instance:scan-domain) and rewrite what should change.');
        }

        return $c->check('rewrite_leftovers', $label, self::GROUP, C::PASS, 'No stored text names the old site.');
    }

    /** The sitemap, if there is one, lists addresses of this site. */
    public function sitemapHost(ReadinessContext $c): C
    {
        $label = 'The sitemap lists this site\'s addresses';
        $result = $this->pages($c)['/sitemap.xml'] ?? null;

        if ($result === null || $result->status !== 200) {
            return $c->check('sitemap_host', $label, self::GROUP, C::SKIP, 'There is no sitemap yet (it is built by the nightly schedule).');
        }

        preg_match_all('~<loc>\s*([^<\s]+)\s*</loc>~i', $result->body, $matches);
        $foreign = [];

        foreach (array_slice($matches[1], 0, 50) as $url) {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));

            if ($host !== '' && $host !== $c->appHost()) {
                $foreign[$host] = true;
            }
        }

        return $foreign === []
            ? $c->check('sitemap_host', $label, self::GROUP, C::PASS, 'The sitemap names only this site.')
            : $c->check('sitemap_host', $label, self::GROUP, C::FAIL, 'The sitemap lists another host: '.implode(', ', array_keys($foreign)).'.', ['hosts' => array_keys($foreign)], 'Build the sitemap again (php artisan sitemap:generate).', overridable: true);
    }

    /** Following redirects never leads back to where it began. */
    public function redirectLoops(ReadinessContext $c): C
    {
        $label = 'No page redirects in a circle';
        $runner = app(SmokeRequestRunner::class);
        $loops = [];

        foreach (['/', '/admin/login'] as $path) {
            if ($runner->follow($c->appHost(), $path)['loop']) {
                $loops[] = $path;
            }
        }

        return $loops === []
            ? $c->check('redirect_loops', $label, self::GROUP, C::PASS, 'The home page and the admin sign-in settle on a page.')
            : $c->check('redirect_loops', $label, self::GROUP, C::FAIL, 'These pages redirect in a circle: '.implode(', ', $loops).'.', ['pages' => $loops], 'Check the canonical host, the https settings and the redirects of the site.');
    }

    /** @return array<string, SmokeResult> path => what came back */
    private function pages(ReadinessContext $c): array
    {
        /** @var array<string, SmokeResult> $pages */
        $pages = $c->memo('smoke_pages', function () use ($c): array {
            $paths = ['/', '/'.$this->locale().'/', '/admin/login', '/robots.txt', '/llms.txt', '/up', '/sitemap.xml'];
            $results = app(SmokeRequestRunner::class)->run($c->appHost(), $paths);

            return array_combine($paths, $results);
        });

        return $pages;
    }

    /** The page at a path after the redirect that adds the language, if any. */
    private function settle(ReadinessContext $c, string $path): SmokeResult
    {
        return app(SmokeRequestRunner::class)->follow($c->appHost(), $path)['last'];
    }

    private function locale(): string
    {
        return (string) (DB::table('languages')->where('is_default', 1)->where('is_active', 1)->value('code') ?? 'en');
    }

    private function partialNote(): string
    {
        return app(SmokeRequestRunner::class)->isLoopback() ? '' : ' (asked inside the application only: the web server in front of it was not part of this check)';
    }
}
