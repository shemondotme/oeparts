<?php

namespace Tests\Feature;

use App\Services\Cleanup\TargetRegistry;
use App\Services\Install\InstallManager;
use App\Services\Instance\CanonicalHostPolicy;
use App\Services\Instance\ConfigReloader;
use App\Services\Instance\InstanceBeginner;
use App\Services\Instance\InstanceEnv;
use App\Services\Instance\InstanceState;
use App\Services\Instance\OriginResetRegistry;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportTarget;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 7.1 (first half): where an imported site will live, what its canonical host becomes, what the arm stage writes to
 * the installation when it closes the site, and which settings an imported site must start with switched off.
 */
class ImportTargetAndBeginTest extends TestCase
{
    use RefreshDatabase;

    private string $dir = '';

    private string $envFile = '';

    private string $envText = "APP_NAME=OeParts\nAPP_KEY=base64:SECRET\nAPP_URL=http://old.local\nOE_MODE=\nMAIL_HOST=smtp.real.example\n";

    private string $ledgerBackup = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/oe-begin-'.getmypid().'-'.uniqid();
        mkdir($this->dir, 0775, true);
        $this->envFile = $this->dir.'/.env';
        file_put_contents($this->envFile, $this->envText);

        $ledger = InstanceState::file()->path();
        $this->ledgerBackup = is_file($ledger) ? (string) file_get_contents($ledger) : '';
        @unlink($ledger);
        @unlink($ledger.'.prev');
        config(['queue.default' => 'sync']);
    }

    protected function tearDown(): void
    {
        $ledger = InstanceState::file()->path();
        @unlink($ledger);
        @unlink($ledger.'.prev');

        if ($this->ledgerBackup !== '') {
            file_put_contents($ledger, $this->ledgerBackup);
        }

        exec('rm -rf '.escapeshellarg($this->dir));

        parent::tearDown();
    }

    private function beginner(): InstanceBeginner
    {
        $path = $this->envFile;
        $installer = new class($path) extends InstallManager
        {
            public function __construct(private readonly string $path) {}

            protected function envPath(): string
            {
                return $this->path;
            }
        };

        return new InstanceBeginner(new InstanceEnv($installer, new ConfigReloader, new InstanceState, $path), new InstanceState);
    }

    private function state(array $options = [], array $package = []): ImportState
    {
        $state = new ImportState('abcdef0123456789', options: $options);
        $state->package = $package + ['source' => ['domain' => 'old.example', 'url' => 'https://old.example'], 'fingerprint' => 'ABCD-1234'];

        return $state;
    }

    // ---- the canonical host ---------------------------------------------------------------------------------------

    #[Test]
    public function a_live_launch_takes_the_new_host_only_when_the_source_had_a_canonical_host(): void
    {
        $policy = new CanonicalHostPolicy;

        $this->assertSame('shop.new.example', $policy->resolve('live', 'www.old.example', 'Shop.New.Example'));
        $this->assertSame('', $policy->resolve('live', '', 'shop.new.example'), 'a source with no canonical host stays without one');
    }

    #[Test]
    public function a_staging_launch_never_has_a_canonical_host(): void
    {
        $this->assertSame('', (new CanonicalHostPolicy)->resolve('staging', 'www.old.example', 'staging.new.example'));
    }

    #[Test]
    public function anything_that_is_not_a_bare_host_is_refused(): void
    {
        $policy = new CanonicalHostPolicy;

        foreach (['https://x.example', 'x.example:8080', 'x.example/path', 'user@x.example', 'x .example', '-x.example', 'x..example', '12345', ''] as $bad) {
            $this->assertFalse(CanonicalHostPolicy::isBare($bad), "'{$bad}'");
        }

        foreach (['shop.example', 'a-b.example.co.uk', 'localhost', 'xn--bcher-kva.example'] as $good) {
            $this->assertTrue(CanonicalHostPolicy::isBare($good), "'{$good}'");
        }

        $this->expectException(\InvalidArgumentException::class);
        $policy->resolve('live', 'https://old.example', 'new.example');
    }

    #[Test]
    public function an_unknown_launch_mode_or_a_non_bare_new_host_is_refused(): void
    {
        foreach ([['production', 'x.example', 'y.example'], ['live', 'x.example', 'https://y.example'], ['live', '', '']] as [$launch, $source, $new]) {
            try {
                (new CanonicalHostPolicy)->resolve($launch, $source, $new);
                $this->fail("{$launch}/{$source}/{$new} must be refused");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ---- the target -------------------------------------------------------------------------------------------------

    #[Test]
    public function the_target_is_read_from_the_options_and_defaults_to_the_address_of_this_site(): void
    {
        config(['app.url' => 'http://localhost']);

        $default = ImportTarget::of($this->state());
        $this->assertSame('http://localhost', $default->url);
        $this->assertSame('live', $default->launch);
        $this->assertSame('production', $default->intent());

        $chosen = ImportTarget::of($this->state(['url' => 'HTTPS://Shop.New.Example:8443/', 'launch' => 'staging', 'also_from' => ['legacy.example', 'LEGACY.example', 'new-sibling.example']]));
        $this->assertSame('https://shop.new.example:8443', $chosen->url);
        $this->assertSame('shop.new.example', $chosen->host);
        $this->assertSame('staging', $chosen->intent());
        $this->assertSame(['legacy.example', 'new-sibling.example'], $chosen->alsoFrom);
    }

    #[Test]
    public function a_number_is_an_address_for_a_test_copy_only(): void
    {
        $test = ImportTarget::of($this->state(['url' => 'http://203.0.113.7:8080', 'launch' => 'staging']));
        $this->assertSame('http://203.0.113.7:8080', $test->url);

        $this->expectException(MigrationException::class);
        ImportTarget::of($this->state(['url' => 'http://203.0.113.7', 'launch' => 'live']));
    }

    #[Test]
    public function the_hosts_to_rewrite_are_the_source_and_the_extras_but_never_the_new_host(): void
    {
        $target = ImportTarget::of($this->state(['url' => 'https://new.example', 'also_from' => ['legacy.example', 'new.example', 'old.example']]));

        $this->assertSame(['old.example', 'legacy.example'], $target->rewriteFrom());
    }

    #[Test]
    public function an_address_the_import_cannot_use_is_refused_before_anything_is_changed(): void
    {
        foreach (['ftp://x.example', 'x.example', 'https://x.example/shop', 'https://user:pw@x.example', 'https://x.example?a=1', 'https://x.example#top', 'https://', 'https://exa mple.com', 'javascript:alert(1)'] as $bad) {
            try {
                ImportTarget::of($this->state(['url' => $bad]));
                $this->fail("'{$bad}' must be refused");
            } catch (MigrationException $e) {
                $this->assertSame(MigrationErrorCode::SiteUrlInvalid, $e->errorCode, $bad);
            }
        }

        $this->expectException(MigrationException::class);
        ImportTarget::of($this->state(['url' => 'https://ok.example', 'launch' => 'production']));
    }

    #[Test]
    public function a_legacy_host_that_is_not_a_bare_host_is_refused(): void
    {
        $this->expectException(MigrationException::class);

        ImportTarget::of($this->state(['url' => 'https://ok.example', 'also_from' => ['https://legacy.example']]));
    }

    // ---- what the arm stage writes ---------------------------------------------------------------------------------

    #[Test]
    public function beginning_puts_the_installation_in_quarantine_with_its_own_address_identity_and_prefixes(): void
    {
        $this->beginner()->begin($this->state(['url' => 'https://new.example', 'launch' => 'staging', 'mail_allowlist' => 'tester@new.example,@team.example']));

        $env = (string) file_get_contents($this->envFile);
        $this->assertStringContainsString('OE_MODE="quarantine"', $env);
        $this->assertStringContainsString('APP_URL="https://new.example"', $env);
        $this->assertStringContainsString('OE_INSTANCE_ID="iabcdef01"', $env);
        $this->assertStringContainsString('CACHE_PREFIX="oe_iabcdef01_"', $env);
        $this->assertStringContainsString('REDIS_PREFIX="oe_iabcdef01_"', $env);
        $this->assertStringContainsString('OE_MAIL_ALLOWLIST="tester@new.example,@team.example"', $env);
        $this->assertStringContainsString('APP_KEY=base64:SECRET', $env, 'the keys it does not own are left exactly as they were');
        $this->assertStringContainsString('MAIL_HOST=smtp.real.example', $env);

        $ledger = (new InstanceState)->read();
        $this->assertSame('iabcdef01', $ledger['instance_id']);
        $this->assertSame('old.example', $ledger['source']['domain']);
        $this->assertSame('ABCD-1234', $ledger['source']['package']);
        $this->assertSame('staging', $ledger['import']['intent']);
        $this->assertSame('abcdef0123456789', $ledger['import']['run_id']);
        $this->assertSame('quarantine', (new InstanceState)->recordedMode());
    }

    #[Test]
    public function beginning_twice_writes_the_same_thing(): void
    {
        $state = $this->state(['url' => 'https://new.example']);

        $this->beginner()->begin($state);
        $first = (string) file_get_contents($this->envFile);
        $this->beginner()->begin($state);

        $this->assertSame($first, (string) file_get_contents($this->envFile));
    }

    #[Test]
    public function a_bad_address_or_identity_changes_nothing(): void
    {
        foreach ([['url' => 'https://x.example/shop'], ['url' => 'https://x.example', 'instance_id' => 'Bad Id!']] as $options) {
            try {
                $this->beginner()->begin($this->state($options));
                $this->fail('expected a refusal');
            } catch (MigrationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame($this->envText, file_get_contents($this->envFile));
        $this->assertNull((new InstanceState)->read()['source']);
    }

    // ---- the settings that start switched off -----------------------------------------------------------------------

    #[Test]
    public function the_cleanup_switches_are_generated_from_the_engines_own_targets(): void
    {
        $qualified = (new OriginResetRegistry)->qualified();

        $this->assertContains('cleanup.auto_enabled', $qualified);
        $this->assertContains('backup.schedule_enabled', $qualified);
        $this->assertContains('updates.auto_apply_security', $qualified);

        foreach ((new TargetRegistry)->all() as $target) {
            $this->assertContains('cleanup.auto_'.$target->key(), $qualified, "the cleanup target {$target->key()} is covered");
        }
    }

    #[Test]
    public function every_on_off_setting_that_arms_something_is_reset_masked_or_consciously_kept(): void
    {
        $registry = new OriginResetRegistry;
        $reset = array_flip($registry->qualified());
        $kept = $registry->kept();
        $seeded = [];
        $uncovered = [];

        foreach (SettingsSeeder::definitions() as $row) {
            $key = $row['group'].'.'.$row['key'];
            $seeded[$key] = true;

            if ($row['type'] !== 'boolean' || ! in_array($row['group'], ['cleanup', 'backup', 'updates', 'seo', 'push', 'integrations', 'performance'], true)) {
                continue;
            }

            if (! preg_match('/^(auto_|schedule_)|_enabled$/', $row['key'])) {
                continue;
            }

            if (isset($reset[$key]) || isset($kept[$key]) || $this->isMasked($row['group'], $row['key'])) {
                continue;
            }

            $uncovered[] = $key;
        }

        $this->assertSame([], $uncovered, 'These settings arm something by themselves: add each to instance.origin_reset.settings, to the staging mask, or to origin_reset.keep with the reason.');

        foreach (array_keys($reset) as $key) {
            $this->assertArrayHasKey($key, $seeded + array_flip(array_map(fn ($t): string => 'cleanup.auto_'.$t->key(), (new TargetRegistry)->all())), "{$key} is in the reset list but is not a setting any more");
        }

        foreach ($kept as $key => $why) {
            $this->assertArrayHasKey($key, $seeded, "{$key} is kept but is not a setting any more");
            $this->assertGreaterThan(10, strlen($why), "{$key} is kept without a reason");
        }
    }

    private function isMasked(string $group, string $key): bool
    {
        foreach ((array) config('instance.overrides.'.$group, []) as $pattern => $_) {
            $pattern = (string) $pattern;

            if ($pattern === $key || (str_ends_with($pattern, '*') && str_starts_with($key, substr($pattern, 0, -1)))) {
                return true;
            }
        }

        return false;
    }
}
