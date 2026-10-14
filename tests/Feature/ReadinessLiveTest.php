<?php

namespace Tests\Feature;

use App\Services\Install\InstallManager;
use App\Services\Instance\ConfigReloader;
use App\Services\Instance\InstanceEnv;
use App\Services\Instance\InstanceMode;
use App\Services\Instance\InstanceModeSwitcher;
use App\Services\Instance\InstanceState;
use App\Services\Instance\Readiness\Checks\HttpChecks;
use App\Services\Instance\Readiness\Checks\WorkerChecks;
use App\Services\Instance\Readiness\HostLeakCrawler;
use App\Services\Instance\Readiness\InstanceQueueProbe;
use App\Services\Instance\Readiness\InstanceQueueProbeJob;
use App\Services\Instance\Readiness\ReadinessCheck;
use App\Services\Instance\Readiness\ReadinessContext;
use App\Services\Instance\Readiness\ReadinessProbe;
use App\Services\Instance\Readiness\ReadinessRegistry;
use App\Services\Instance\Readiness\ReadinessReport;
use App\Services\Instance\Readiness\SmokeRequestRunner;
use App\Services\Instance\Readiness\SmokeResult;
use App\Services\Instance\SafeMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 7.4: the readiness checks that need the site to answer and a worker to pick up a job (the "live" phase), and the switch
 * of the mode that proves the workers followed.
 */
class ReadinessLiveTest extends TestCase
{
    use RefreshDatabase;

    private string $dir = '';

    private string $ledgerBackup = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/oe-live-'.getmypid().'-'.uniqid();
        mkdir($this->dir, 0775, true);

        $ledger = InstanceState::file()->path();
        $this->ledgerBackup = is_file($ledger) ? (string) file_get_contents($ledger) : '';
        @unlink($ledger);
        @unlink($ledger.'.prev');
        @unlink(InstanceQueueProbeJob::file()->path());
        config(['app.url' => 'https://shop.example', 'queue.default' => 'sync', 'instance.id' => 'iabc123']);
    }

    protected function tearDown(): void
    {
        $ledger = InstanceState::file()->path();
        @unlink($ledger);
        @unlink($ledger.'.prev');
        @unlink(InstanceQueueProbeJob::file()->path());
        @unlink(InstanceQueueProbeJob::file()->path().'.prev');

        if ($this->ledgerBackup !== '') {
            file_put_contents($ledger, $this->ledgerBackup);
        }

        exec('rm -rf '.escapeshellarg($this->dir));

        parent::tearDown();
    }

    // ---- fixtures ---------------------------------------------------------------------------------------------

    private function context(): ReadinessContext
    {
        return ReadinessContext::make();
    }

    /** @param  array<string, SmokeResult|callable>  $pages  path => canned result (or a function of the host) */
    private function pages(array $pages): SmokeRequestRunner
    {
        return new SmokeRequestRunner(function (string $host, string $path) use ($pages): SmokeResult {
            $page = $pages[$path] ?? new SmokeResult($path, 200, null, '<html></html>');

            return $page instanceof SmokeResult ? $page : $page($host);
        });
    }

    private function useRunner(SmokeRequestRunner $runner): void
    {
        $this->app->instance(SmokeRequestRunner::class, $runner);
    }

    private function oldSite(string ...$hosts): void
    {
        (new InstanceState)->update(function (array $ledger) use ($hosts): array {
            $ledger['source'] = ['domain' => $hosts[0] ?? null];
            $ledger['import'] = ['also_from' => array_slice($hosts, 1)];

            return $ledger;
        });
    }

    private function probeThatSays(?array $answer, bool $available = true): InstanceQueueProbe
    {
        return new class($answer, $available) extends InstanceQueueProbe
        {
            public int $asked = 0;

            public function __construct(private readonly ?array $answer, private readonly bool $on) {}

            public function available(): bool
            {
                return $this->on;
            }

            public function ask(?int $waitSeconds = null): ?array
            {
                $this->asked++;

                return $this->answer;
            }
        };
    }

    private function answer(string $mode, string $id = 'iabc123'): array
    {
        return ['nonce' => 'n', 'at' => now()->toIso8601String(), 'pid' => 1, 'mode' => $mode, 'instance_id' => $id, 'queue' => 'default', 'connection' => 'database'];
    }

    // ---- looking for the old address in a page ---------------------------------------------------------------------------

    #[Test]
    public function the_old_address_is_found_in_the_places_a_visitor_never_sees(): void
    {
        $html = <<<'HTML'
<html><head>
<link rel="canonical" href="https://www.old.example/en/">
<link rel="alternate" hreflang="de" href="https://old.example/de/">
<link rel="alternate" hreflang="fr" href="https://shop.example/fr/">
<meta property="og:url" content="https://old.example/en/">
<meta property="og:image" content="//old.example/img/logo.png">
<script type="application/ld+json">{"@context":"https://schema.org","url":"https://old.example/","logo":{"url":"https://cdn.old.example/l.png"},"sameAs":["https://twitter.com/x","https://shop.example/"]}</script>
</head><body><a href="https://old.example/body-link">a plain link in the body is not looked at</a></body></html>
HTML;

        $found = (new HostLeakCrawler)->inspect($html, ['old.example']);

        $this->assertEqualsCanonicalizing(['canonical', 'hreflang', 'og:url', 'og:image', 'json-ld'], array_values(array_unique(array_column($found, 'where'))));
        $this->assertCount(5, $found, 'canonical, one hreflang, og:url, og:image and one JSON-LD address (the sub-domain is another host)');
    }

    #[Test]
    public function a_clean_page_and_an_empty_list_find_nothing(): void
    {
        $crawler = new HostLeakCrawler;
        $page = '<html><head><link rel="canonical" href="https://shop.example/en/"><meta property="og:url" content="https://shop.example/en/"></head></html>';

        $this->assertSame([], $crawler->inspect($page, ['old.example']));
        $this->assertSame([], $crawler->inspect('<link rel="canonical" href="https://old.example/">', []), 'nothing to look for');
        $this->assertSame([], $crawler->inspect('', ['old.example']));
        $this->assertSame([], $crawler->inspect('not html at all <<<', ['old.example']));
    }

    // ---- asking the site ---------------------------------------------------------------------------------------------

    #[Test]
    public function a_redirect_chain_is_followed_and_a_circle_is_noticed(): void
    {
        $runner = $this->pages([
            '/' => new SmokeResult('/', 302, 'https://shop.example/en'),
            '/en' => new SmokeResult('/en', 200, null, 'home'),
            '/a' => new SmokeResult('/a', 301, 'https://shop.example/b'),
            '/b' => new SmokeResult('/b', 301, 'https://shop.example/a'),
            '/away' => new SmokeResult('/away', 301, 'https://other.example/x'),
        ]);

        $settled = $runner->follow('shop.example', '/');
        $this->assertFalse($settled['loop']);
        $this->assertSame(1, $settled['hops']);
        $this->assertSame('home', $settled['last']->body);

        $this->assertTrue($runner->follow('shop.example', '/a')['loop']);
        $this->assertFalse($runner->follow('shop.example', '/away')['loop'], 'leaving the site is not a circle');
    }

    #[Test]
    public function the_site_can_be_asked_inside_this_process_when_no_network_address_is_given(): void
    {
        $runner = new SmokeRequestRunner;

        $this->assertFalse($runner->isLoopback());
        $result = $runner->fetch('shop.example', '/up');

        $this->assertSame(200, $result->status);
    }

    // ---- the page checks ---------------------------------------------------------------------------------------------

    #[Test]
    public function a_page_that_sends_visitors_to_another_host_or_fails_is_a_failure(): void
    {
        $this->useRunner($this->pages(['/' => new SmokeResult('/', 302, 'https://old.example/en')]));
        $bad = (new HttpChecks)->crossHostRedirects($this->context());
        $this->assertSame('fail', $bad->status);
        $this->assertSame(['/' => 'sends visitors to another host'], $bad->meta['pages']);

        $this->useRunner($this->pages(['/admin/login' => new SmokeResult('/admin/login', 500)]));
        $this->assertSame('fail', (new HttpChecks)->crossHostRedirects($this->context())->status);

        $this->useRunner($this->pages(['/' => new SmokeResult('/', 302, 'https://shop.example/en'), '/robots.txt' => new SmokeResult('/robots.txt', 404)]));
        $ok = (new HttpChecks)->crossHostRedirects($this->context());
        $this->assertSame('pass', $ok->status, 'a redirect to its own host and a 404 are fine');
        $this->assertTrue($ok->meta['partial'], 'asked inside the application only: the report says so');
    }

    #[Test]
    public function the_pages_must_not_name_the_old_site(): void
    {
        $this->assertSame('skip', (new HttpChecks)->oldHostInPages($this->context())->status, 'no old address known');

        $this->oldSite('old.example');
        $leaky = '<html><head><link rel="canonical" href="https://old.example/en/"></head></html>';
        $this->useRunner($this->pages(['/' => new SmokeResult('/', 200, null, $leaky)]));

        $found = (new HttpChecks)->oldHostInPages($this->context());
        $this->assertSame('fail', $found->status);
        $this->assertSame('canonical', $found->meta['places'][0]['where']);

        $this->useRunner($this->pages([]));
        $this->assertSame('pass', (new HttpChecks)->oldHostInPages($this->context())->status);
    }

    #[Test]
    public function the_sitemap_must_list_addresses_of_this_site(): void
    {
        $this->useRunner($this->pages(['/sitemap.xml' => new SmokeResult('/sitemap.xml', 404)]));
        $this->assertSame('skip', (new HttpChecks)->sitemapHost($this->context())->status);

        $xml = '<urlset><url><loc>https://shop.example/en/a</loc></url><url><loc>https://old.example/en/b</loc></url></urlset>';
        $this->useRunner($this->pages(['/sitemap.xml' => new SmokeResult('/sitemap.xml', 200, null, $xml)]));
        $bad = (new HttpChecks)->sitemapHost($this->context());
        $this->assertSame('fail', $bad->status);
        $this->assertTrue($bad->overridable, 'a stale sitemap is the one page failure a person may waive');
        $this->assertSame(['old.example'], $bad->meta['hosts']);

        $this->useRunner($this->pages(['/sitemap.xml' => new SmokeResult('/sitemap.xml', 200, null, '<urlset><url><loc>https://shop.example/en/a</loc></url></urlset>')]));
        $this->assertSame('pass', (new HttpChecks)->sitemapHost($this->context())->status);
    }

    #[Test]
    public function a_circle_of_redirects_is_a_failure(): void
    {
        $this->useRunner($this->pages([
            '/' => new SmokeResult('/', 301, 'https://shop.example/en'),
            '/en' => new SmokeResult('/en', 301, 'https://shop.example/'),
        ]));

        $loop = (new HttpChecks)->redirectLoops($this->context());

        $this->assertSame('fail', $loop->status);
        $this->assertContains('/', $loop->meta['pages']);
    }

    // ---- the worker -------------------------------------------------------------------------------------------------------

    #[Test]
    public function with_a_sync_queue_there_is_no_worker_to_look_for_and_the_checks_say_so(): void
    {
        $this->app->instance(InstanceQueueProbe::class, $this->probeThatSays(null, available: false));

        $this->assertSame('skip', (new WorkerChecks)->worker($this->context())->status);
        $this->assertSame('skip', (new WorkerChecks)->workerMode($this->context())->status);
    }

    #[Test]
    public function no_answer_from_a_worker_is_a_warning_a_person_may_waive_not_a_stop(): void
    {
        $this->app->instance(InstanceQueueProbe::class, $this->probeThatSays(null));

        $worker = (new WorkerChecks)->worker($this->context());
        $this->assertSame('warn', $worker->status);
        $this->assertTrue($worker->overridable);
        $this->assertSame('skip', (new WorkerChecks)->workerMode($this->context())->status, 'no worker answered, so none can be out of date');
    }

    #[Test]
    public function a_worker_that_still_believes_the_old_mode_blocks_the_launch_and_cannot_be_waived(): void
    {
        config(['instance.mode' => null]); // the site is production now
        $this->app->instance(InstanceQueueProbe::class, $this->probeThatSays($this->answer('quarantine')));

        $mode = (new WorkerChecks)->workerMode($this->context());

        $this->assertSame('fail', $mode->status);
        $this->assertFalse($mode->overridable);
        $this->assertSame(['site' => 'production', 'worker' => 'quarantine'], $mode->meta);

        $report = new ReadinessReport([$mode], true, now()->toIso8601String());
        $this->assertFalse($report->canRelease(['queue_worker_mode']), 'asking for a waiver changes nothing');
    }

    #[Test]
    public function a_worker_that_agrees_passes_and_one_answer_serves_both_checks(): void
    {
        config(['instance.mode' => 'staging']);
        $probe = $this->probeThatSays($this->answer('staging'));
        $this->app->instance(InstanceQueueProbe::class, $probe);
        $context = $this->context();

        $this->assertSame('pass', (new WorkerChecks)->worker($context)->status);
        $this->assertSame('pass', (new WorkerChecks)->workerMode($context)->status);
        $this->assertSame(1, $probe->asked, 'one probe job for the whole report');

        $other = $probe::class;
        $this->app->instance(InstanceQueueProbe::class, $this->probeThatSays($this->answer('staging', 'another-installation')));
        $this->assertSame('fail', (new WorkerChecks)->workerMode($this->context())->status, 'a worker of another installation is as wrong as a stale one');
        $this->assertNotSame('', $other);
    }

    #[Test]
    public function the_probe_job_reports_what_the_worker_process_believes(): void
    {
        config(['instance.mode' => 'quarantine', 'queue.default' => 'database']);

        (new InstanceQueueProbeJob('nonce-1'))->handle();

        $seen = InstanceQueueProbeJob::file()->read();
        $this->assertSame('nonce-1', $seen['nonce']);
        $this->assertSame('quarantine', $seen['mode']);
        $this->assertSame('iabc123', $seen['instance_id']);
    }

    #[Test]
    public function a_real_worker_answers_the_probe_and_an_old_answer_is_never_mistaken_for_a_new_one(): void
    {
        config(['instance.mode' => 'quarantine', 'queue.default' => 'database']);
        InstanceQueueProbeJob::file()->write(['nonce' => 'stale-answer-from-before', 'mode' => 'production', 'instance_id' => 'iabc123', 'at' => now()->toIso8601String()]);

        // The "worker" is a real queue:work run once, in this process, when the probe is waiting.
        $probe = new InstanceQueueProbe(function (): void {
            Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--queue' => 'default,migration', '--tries' => 1]);
        });

        $answer = $probe->ask(5);

        $this->assertNotNull($answer);
        $this->assertNotSame('stale-answer-from-before', $answer['nonce']);
        $this->assertSame('quarantine', $answer['mode']);
        $this->assertSame('database', $answer['connection']);
    }

    #[Test]
    public function with_no_worker_the_probe_waits_and_comes_back_empty_handed(): void
    {
        config(['queue.default' => 'database']);

        $this->assertNull((new InstanceQueueProbe(fn () => null))->ask(0));
    }

    // ---- switching the mode -----------------------------------------------------------------------------------------------

    private function switcher(?InstanceQueueProbe $probe = null): InstanceModeSwitcher
    {
        $path = $this->dir.'/.env';
        file_put_contents($path, "APP_NAME=OeParts\nOE_MODE=quarantine\nOE_INSTANCE_ID=iabc123\n");
        $installer = new class($path) extends InstallManager
        {
            public function __construct(private readonly string $path) {}

            protected function envPath(): string
            {
                return $this->path;
            }
        };

        return new InstanceModeSwitcher(new InstanceEnv($installer, new ConfigReloader, new InstanceState, $path), $probe ?? $this->probeThatSays(null, available: false), app(SafeMode::class));
    }

    #[Test]
    public function switching_writes_the_new_mode_and_tells_the_workers_to_restart_first(): void
    {
        config(['instance.mode' => 'quarantine', 'queue.default' => 'database']);
        Cache::forget('illuminate:queue:restart');
        $switcher = $this->switcher($this->probeThatSays($this->answer('production')));

        $result = $switcher->switch(InstanceMode::Production, 'test', 2);

        $this->assertSame(['from' => 'quarantine', 'to' => 'production', 'worker' => 'confirmed', 'wrote' => true], $result);
        $this->assertStringContainsString('OE_MODE="production"', (string) file_get_contents($this->dir.'/.env'));
        $this->assertNotNull(Cache::get('illuminate:queue:restart'), 'the restart signal was sent');
    }

    #[Test]
    public function a_worker_that_keeps_the_old_mode_is_reported_as_stale_and_none_at_all_as_none(): void
    {
        config(['instance.mode' => 'quarantine', 'queue.default' => 'database']);

        $stale = $this->switcher($this->probeThatSays($this->answer('quarantine')))->switch(InstanceMode::Production, 'test', 1);
        $this->assertSame('stale', $stale['worker']);

        config(['instance.mode' => 'quarantine']);
        $none = $this->switcher($this->probeThatSays(null))->switch(InstanceMode::Production, 'test', 1);
        $this->assertSame('none_answered', $none['worker']);
    }

    #[Test]
    public function with_a_sync_queue_there_is_no_worker_to_wait_for_and_an_unchanged_mode_writes_nothing(): void
    {
        config(['instance.mode' => 'quarantine', 'queue.default' => 'sync']);
        $switcher = $this->switcher();

        $same = $switcher->switch(InstanceMode::Quarantine, 'test');
        $this->assertFalse($same['wrote']);
        $this->assertSame('skipped', $same['worker']);

        $changed = $this->switcher()->switch(InstanceMode::Staging, 'test');
        $this->assertTrue($changed['wrote']);
        $this->assertSame('skipped', $changed['worker']);
    }

    // ---- phases -----------------------------------------------------------------------------------------------------------

    #[Test]
    public function the_live_checks_are_a_phase_of_their_own_and_a_static_report_alone_can_never_release(): void
    {
        $registry = new ReadinessRegistry;
        $static = array_map(fn (ReadinessProbe $p): string => $p->key, $registry->probes([ReadinessProbe::STATIC]));
        $live = array_map(fn (ReadinessProbe $p): string => $p->key, $registry->probes([ReadinessProbe::LIVE]));

        $this->assertContains('rows_vs_manifest', $static);
        $this->assertNotContains('queue_worker_mode', $static);
        $this->assertEqualsCanonicalizing(['cross_host_redirects', 'old_host_in_pages', 'rewrite_leftovers', 'sitemap_host', 'redirect_loops', 'queue_worker', 'queue_worker_mode'], $live);

        $onlyStatic = new ReadinessReport([new ReadinessCheck('a', 'a', 'data', 'pass')], true, now()->toIso8601String(), [ReadinessProbe::STATIC]);
        $this->assertFalse($onlyStatic->canRelease());
        $this->assertTrue((new ReadinessReport([new ReadinessCheck('a', 'a', 'data', 'pass')], true, now()->toIso8601String()))->canRelease());
    }

    #[Test]
    public function the_old_hosts_of_a_site_are_what_the_ledger_remembers_of_where_it_came_from(): void
    {
        $this->oldSite('old.example', 'legacy.example');
        (new InstanceState)->update(function (array $ledger): array {
            $ledger['import']['source_canonical_host'] = 'www.old.example';

            return $ledger;
        });

        $this->assertEqualsCanonicalizing(['old.example', 'legacy.example', 'www.old.example'], $this->context()->oldHosts());

        config(['app.url' => 'https://old.example']);
        $this->assertNotContains('old.example', $this->context()->oldHosts(), 'the site\'s own host is never an old one');
        $this->assertSame(0, DB::table('settings')->where('key', 'definitely-not-here')->count());
    }
}
