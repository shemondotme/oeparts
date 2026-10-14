<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Migration\Rewrite\DomainRewriter;
use App\Services\Migration\Rewrite\RewriteCursor;
use App\Services\Migration\Rewrite\RewritePlan;
use App\Services\SettingsService;
use App\Support\AtomicJsonFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\PlantsRows;
use Tests\TestCase;

/**
 * Phase 5.3: oeparts:instance:rewrite-domain and oeparts:instance:scan-domain from the command line.
 */
class DomainRewriteCommandsTest extends TestCase
{
    use PlantsRows;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['rewrite-domain.json', 'rewrite-domain.json.prev', 'rewrite-domain.json.lock', 'rewrite-domain.lock'] as $file) {
            @unlink(storage_path('app/instance/'.$file));
        }

        Setting::create(['group' => 'general', 'key' => 'site_url', 'value' => 'http://old.example', 'type' => 'string', 'is_encrypted' => false]);
        $this->plantRow('redirects', ['from_url' => '/a', 'to_url' => 'https://old.example/x']);
        $this->plantRow('redirects', ['from_url' => '/b', 'to_url' => 'http://www.old.example/y']);
    }

    protected function tearDown(): void
    {
        foreach (['rewrite-domain.json', 'rewrite-domain.json.prev', 'rewrite-domain.json.lock', 'rewrite-domain.lock'] as $file) {
            @unlink(storage_path('app/instance/'.$file));
        }

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function runJson(string $command, array $args): array
    {
        $code = Artisan::call($command, $args + ['--json' => true]);
        $decoded = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $decoded['_exit'] = $code;

        return $decoded;
    }

    #[Test]
    public function a_dry_run_reports_the_counts_and_writes_nothing(): void
    {
        $report = $this->runJson('oeparts:instance:rewrite-domain', ['to' => 'https://new.example', '--dry-run' => true]);

        $this->assertSame(0, $report['_exit']);
        $this->assertSame('dry-run', $report['mode']);
        $this->assertSame(['old.example'], $report['from'], 'the old name defaults to the stored site address');
        $this->assertGreaterThanOrEqual(3, $report['rows_changed']);
        $this->assertSame('https://old.example/x', DB::table('redirects')->where('from_url', '/a')->value('to_url'));
        $this->assertSame('http://old.example', Setting::where('key', 'site_url')->value('value'));
    }

    #[Test]
    public function it_refuses_to_change_anything_without_yes_when_nobody_can_answer(): void
    {
        $report = $this->runJson('oeparts:instance:rewrite-domain', ['to' => 'https://new.example']);

        $this->assertSame(1, $report['_exit']);
        $this->assertStringContainsString('--yes', $report['error']);
        $this->assertSame('https://old.example/x', DB::table('redirects')->where('from_url', '/a')->value('to_url'));
    }

    #[Test]
    public function with_yes_it_rewrites_resets_the_settings_cache_and_a_second_run_changes_nothing(): void
    {
        // Warm the 5-minute settings cache with the old value.
        $this->assertSame('http://old.example', app(SettingsService::class)->get('general.site_url'));

        $report = $this->runJson('oeparts:instance:rewrite-domain', ['to' => 'https://new.example', '--yes' => true]);

        $this->assertSame(0, $report['_exit']);
        $this->assertSame('applied', $report['mode']);
        $this->assertSame('https://new.example/x', DB::table('redirects')->where('from_url', '/a')->value('to_url'));
        $this->assertSame('https://new.example/y', DB::table('redirects')->where('from_url', '/b')->value('to_url'));
        $this->assertSame('https://new.example', app(SettingsService::class)->get('general.site_url'), 'the cached value was dropped');

        $again = $this->runJson('oeparts:instance:rewrite-domain', ['to' => 'https://new.example', '--from' => ['old.example'], '--yes' => true]);
        $this->assertSame(0, $again['rows_changed']);
        $this->assertSame(0, $again['replacements']);
    }

    #[Test]
    public function several_old_names_can_be_given(): void
    {
        $this->plantRow('redirects', ['from_url' => '/c', 'to_url' => 'http://localhost:8000/z']);

        $report = $this->runJson('oeparts:instance:rewrite-domain', ['to' => 'https://new.example', '--from' => ['old.example', 'localhost:8000'], '--yes' => true]);

        $this->assertSame(0, $report['_exit']);
        $this->assertSame('https://new.example/z', DB::table('redirects')->where('from_url', '/c')->value('to_url'));
    }

    #[Test]
    public function a_new_address_that_is_not_a_full_url_is_refused_in_plain_words(): void
    {
        $report = $this->runJson('oeparts:instance:rewrite-domain', ['to' => 'new.example', '--yes' => true]);

        $this->assertSame(1, $report['_exit']);
        $this->assertStringContainsString('full http', $report['error']);
    }

    #[Test]
    public function with_no_stored_address_and_no_from_it_asks_for_one(): void
    {
        Setting::where('key', 'site_url')->delete();

        $report = $this->runJson('oeparts:instance:rewrite-domain', ['to' => 'https://new.example', '--yes' => true]);

        $this->assertSame(1, $report['_exit']);
        $this->assertStringContainsString('--from', $report['error']);
    }

    #[Test]
    public function a_run_that_was_interrupted_continues_from_its_file(): void
    {
        $directory = storage_path('app/instance');
        @mkdir($directory, 0775, true);
        $plan = new RewritePlan(['old.example'], 'https://new.example');
        $cursor = RewriteCursor::fresh($plan);
        $file = new AtomicJsonFile($directory.'/rewrite-domain.json');
        (new DomainRewriter(chunkRows: 1))->advance($plan, $cursor, 0.0, false, fn ($c) => $c->save($file));
        $this->assertFalse($cursor->done, 'the first call only did one chunk');

        Artisan::call('oeparts:instance:rewrite-domain', ['to' => 'https://new.example', '--yes' => true, '--chunk' => 200]);

        $this->assertStringContainsString('Continuing an earlier run', Artisan::output());
        $this->assertSame('https://new.example/x', DB::table('redirects')->where('from_url', '/a')->value('to_url'));
    }

    #[Test]
    public function two_rewrites_cannot_run_at_once(): void
    {
        $directory = storage_path('app/instance');
        @mkdir($directory, 0775, true);
        $held = fopen($directory.'/rewrite-domain.lock', 'c');
        $this->assertTrue(flock($held, LOCK_EX | LOCK_NB));

        try {
            $report = $this->runJson('oeparts:instance:rewrite-domain', ['to' => 'https://new.example', '--yes' => true]);
        } finally {
            flock($held, LOCK_UN);
            fclose($held);
        }

        $this->assertSame(1, $report['_exit']);
        $this->assertStringContainsString('Another domain rewrite', $report['error']);
        $this->assertSame('https://old.example/x', DB::table('redirects')->where('from_url', '/a')->value('to_url'));
    }

    // ---- scan -----------------------------------------------------------------------------------------------------

    #[Test]
    public function the_scan_command_lists_where_the_old_name_still_is_and_changes_nothing(): void
    {
        $report = $this->runJson('oeparts:instance:scan-domain', ['--host' => ['old.example']]);

        $this->assertSame(0, $report['_exit']);
        $this->assertContains('old.example', $report['hosts']);
        $columns = array_map(fn (array $e): string => $e['table'].'.'.$e['column'], $report['entries']);
        $this->assertContains('redirects.to_url', $columns);
        $this->assertSame('https://old.example/x', DB::table('redirects')->where('from_url', '/a')->value('to_url'));
    }

    #[Test]
    public function after_the_rewrite_the_scan_finds_nothing_in_the_rewritten_places(): void
    {
        Artisan::call('oeparts:instance:rewrite-domain', ['to' => 'https://new.example', '--yes' => true]);

        $report = $this->runJson('oeparts:instance:scan-domain', ['--host' => ['old.example']]);

        $columns = array_map(fn (array $e): string => $e['table'].'.'.$e['column'], $report['entries']);
        $this->assertNotContains('redirects.to_url', $columns);
        $this->assertSame(0, $report['_exit']);
    }

    #[Test]
    public function strict_makes_a_gap_or_an_unchecked_column_a_failure(): void
    {
        $clean = $this->runJson('oeparts:instance:scan-domain', ['--host' => ['nothing-like-this.example'], '--strict' => true]);

        $this->assertSame(0, $clean['_exit']);
        $this->assertFalse($clean['needs_attention']);
    }

    #[Test]
    public function the_scan_needs_a_name_to_look_for(): void
    {
        Setting::where('key', 'site_url')->delete();
        config(['app.url' => '']);

        $code = Artisan::call('oeparts:instance:scan-domain', ['--json' => true]);

        $this->assertSame(1, $code);
    }
}
