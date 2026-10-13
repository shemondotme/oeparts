<?php

namespace Tests\Feature;

use App\Services\Install\InstallManager;
use App\Services\Instance\ConfigReloader;
use App\Services\Instance\InstanceCacheReset;
use App\Services\Instance\InstanceEnv;
use App\Services\Instance\InstanceState;
use Dotenv\Dotenv;
use Illuminate\Cache\CacheManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 4.1: the one door to .env for the instance code. A short list of keys, plain characters only, written
 * atomically through the installer's own writer, with the queue workers told to restart FIRST whenever the mode,
 * the mail policy or a cache prefix changes.
 */
class InstanceEnvTest extends TestCase
{
    private string $dir = '';

    private string $env = '';

    private string $ledgerBackup = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-instenv-'.getmypid().'-'.uniqid();
        mkdir($this->dir, 0775, true);
        $this->env = $this->dir.'/.env';
        file_put_contents($this->env, "APP_NAME=OeParts\nAPP_KEY=base64:SECRET\nAPP_URL=http://localhost\nOE_MODE=\nMAIL_HOST=smtp.real.example\n");

        $path = InstanceState::file()->path();
        $this->ledgerBackup = is_file($path) ? (string) file_get_contents($path) : '';
        @unlink($path);
        @unlink($path.'.prev');
        config(['queue.default' => 'database']);
    }

    protected function tearDown(): void
    {
        $path = InstanceState::file()->path();
        @unlink($path);
        @unlink($path.'.prev');
        if ($this->ledgerBackup !== '') {
            file_put_contents($path, $this->ledgerBackup);
        }
        exec('rm -rf '.escapeshellarg($this->dir));

        parent::tearDown();
    }

    private function instanceEnv(): InstanceEnv
    {
        $env = $this->env;
        $installer = new class($env) extends InstallManager
        {
            public function __construct(private readonly string $path) {}

            protected function envPath(): string
            {
                return $this->path;
            }
        };

        return new InstanceEnv($installer, new ConfigReloader, new InstanceState, $env);
    }

    private function envText(): string
    {
        return (string) file_get_contents($this->env);
    }

    // ---- what may be written -------------------------------------------------------------------------

    #[Test]
    #[DataProvider('forbiddenKeys')]
    public function a_key_that_is_not_on_the_list_is_refused_and_nothing_is_written(string $key): void
    {
        $before = $this->envText();

        try {
            $this->instanceEnv()->apply([$key => 'x'], 'test');
            $this->fail("{$key} must be refused");
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString($key, $e->getMessage());
        }

        $this->assertSame($before, $this->envText());
    }

    /** @return array<string, array{0: string}> */
    public static function forbiddenKeys(): array
    {
        return [
            'app key' => ['APP_KEY'], 'app env' => ['APP_ENV'], 'mail host' => ['MAIL_HOST'], 'proxies' => ['TRUSTED_PROXIES'],
            'db password' => ['DB_PASSWORD'], 'backup key' => ['OE_BACKUP_KEY'], 'lower case' => ['oe_mode'], 'injection' => ["OE_MODE\nAPP_KEY"],
        ];
    }

    #[Test]
    #[DataProvider('dangerousValues')]
    public function a_value_that_could_break_out_of_its_line_is_refused(string $value): void
    {
        $before = $this->envText();

        $this->expectException(\InvalidArgumentException::class);

        try {
            $this->instanceEnv()->apply(['OE_INSTANCE_ID' => $value], 'test');
        } finally {
            $this->assertSame($before, $this->envText());
        }
    }

    /** @return array<string, array{0: string}> */
    public static function dangerousValues(): array
    {
        return ['quote' => ['a"b'], 'dollar' => ['a$b'], 'backslash' => ['a\\b'], 'newline' => ["a\nAPP_KEY=x"], 'backtick' => ['a`b'], 'semicolon' => ['a;b'], 'hash' => ['a#b']];
    }

    #[Test]
    public function the_mode_must_be_one_of_the_three(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->instanceEnv()->apply(['OE_MODE' => 'stagging'], 'test');
    }

    #[Test]
    public function app_url_must_be_a_host_with_no_path_and_no_other_scheme(): void
    {
        foreach (['ftp://shop.example', 'shop.example', 'https://shop.example/sub', 'https://'] as $bad) {
            try {
                $this->instanceEnv()->apply(['APP_URL' => $bad], 'test');
                $this->fail("{$bad} must be refused");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->instanceEnv()->apply(['APP_URL' => 'https://shop.example:8443'], 'test');
        $this->assertStringContainsString('APP_URL="https://shop.example:8443"', $this->envText());
    }

    // ---- writing -------------------------------------------------------------------------------------

    #[Test]
    public function it_changes_only_the_asked_keys_and_leaves_every_other_line_exactly_as_it_was(): void
    {
        $result = $this->instanceEnv()->apply(['OE_MODE' => 'staging', 'OE_INSTANCE_ID' => 'stg1'], 'tester');

        $text = $this->envText();
        $this->assertStringContainsString('APP_KEY=base64:SECRET', $text);
        $this->assertStringContainsString('MAIL_HOST=smtp.real.example', $text);
        $this->assertStringContainsString('OE_MODE="staging"', $text);
        $this->assertStringContainsString('OE_INSTANCE_ID="stg1"', $text);
        $this->assertSame(['OE_MODE', 'OE_INSTANCE_ID'], array_keys($result['changed']));
        $this->assertSame(['from' => '', 'to' => 'staging'], $result['changed']['OE_MODE']);
        $this->assertFileDoesNotExist($this->env.'.tmp', 'written atomically: no temp file left');
    }

    #[Test]
    public function writing_the_value_that_is_already_there_does_nothing_at_all(): void
    {
        $this->instanceEnv()->apply(['OE_MODE' => 'staging'], 'tester');
        Artisan::spy();
        $mtime = filemtime($this->env);
        $history = count((new InstanceState)->read()['env_history']);

        $result = $this->instanceEnv()->apply(['OE_MODE' => 'staging'], 'tester');

        $this->assertSame(['changed' => [], 'restarted' => false, 'reloaded' => []], $result);
        Artisan::shouldNotHaveReceived('call');
        $this->assertSame($mtime, filemtime($this->env));
        $this->assertCount($history, (new InstanceState)->read()['env_history']);
    }

    #[Test]
    public function a_value_survives_a_round_trip_through_the_dotenv_reader(): void
    {
        $this->instanceEnv()->apply(['OE_MAIL_ALLOWLIST' => 'dev@example.com,@team.example'], 'tester');

        $parsed = Dotenv::parse($this->envText());
        $this->assertSame('dev@example.com,@team.example', $parsed['OE_MAIL_ALLOWLIST']);
        $this->assertSame('dev@example.com,@team.example', $this->instanceEnv()->current('OE_MAIL_ALLOWLIST'));
    }

    // ---- the ledger ----------------------------------------------------------------------------------

    #[Test]
    public function every_change_is_recorded_with_who_made_it_and_a_mode_change_enters_the_mode_history(): void
    {
        $this->instanceEnv()->apply(['OE_MODE' => 'quarantine', 'OE_INSTANCE_ID' => 'imp1'], 'import:7');
        $this->instanceEnv()->apply(['OE_MODE' => 'production'], 'go-live');

        $state = (new InstanceState)->read();

        $this->assertCount(2, $state['env_history']);
        $this->assertSame('import:7', $state['env_history'][0]['by']);
        $this->assertSame('imp1', $state['instance_id']);
        $this->assertSame([['from' => 'production', 'to' => 'quarantine'], ['from' => 'quarantine', 'to' => 'production']], array_map(fn (array $m): array => ['from' => $m['from'], 'to' => $m['to']], $state['mode_history']));
        $this->assertSame('production', (new InstanceState)->recordedMode());
    }

    // ---- the workers ---------------------------------------------------------------------------------

    #[Test]
    #[DataProvider('restartKeys')]
    public function a_change_to_the_mode_mail_policy_or_a_prefix_restarts_the_workers_on_a_real_queue(string $key, string $value): void
    {
        Artisan::spy();

        $result = $this->instanceEnv()->apply([$key => $value], 'tester');

        $this->assertTrue($result['restarted']);
        Artisan::shouldHaveReceived('call')->with('queue:restart')->once();
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function restartKeys(): array
    {
        return ['mode' => ['OE_MODE', 'staging'], 'instance' => ['OE_INSTANCE_ID', 'x1'], 'mail policy' => ['OE_MAIL_POLICY', 'allowlist'], 'allowlist' => ['OE_MAIL_ALLOWLIST', 'a@b.test'], 'cache prefix' => ['CACHE_PREFIX', 'stg_cache_'], 'redis prefix' => ['REDIS_PREFIX', 'stg_redis_']];
    }

    #[Test]
    public function a_change_that_workers_do_not_care_about_does_not_restart_them(): void
    {
        Artisan::spy();

        $result = $this->instanceEnv()->apply(['APP_URL' => 'https://new.example', 'OE_NONPROD_HTTP_CRON' => 'true'], 'tester');

        $this->assertFalse($result['restarted']);
        Artisan::shouldNotHaveReceived('call', ['queue:restart']);
    }

    #[Test]
    public function on_the_sync_queue_there_is_nobody_to_restart(): void
    {
        config(['queue.default' => 'sync']);
        Artisan::spy();

        $result = $this->instanceEnv()->apply(['OE_MODE' => 'staging'], 'tester');

        $this->assertFalse($result['restarted']);
        Artisan::shouldNotHaveReceived('call', ['queue:restart']);
    }

    #[Test]
    public function the_restart_signal_goes_out_before_the_file_is_written(): void
    {
        $seen = null;
        Artisan::shouldReceive('call')->with('queue:restart')->once()->andReturnUsing(function () use (&$seen): int {
            $seen = $this->envText();

            return 0;
        });
        Artisan::shouldReceive('call')->with('config:clear')->andReturn(0);

        $this->instanceEnv()->apply(['OE_MODE' => 'staging'], 'tester');

        $this->assertStringNotContainsString('staging', (string) $seen, 'at the moment of the signal .env still had the old value');
        $this->assertStringContainsString('staging', $this->envText());
    }

    #[Test]
    public function if_the_signal_cannot_be_sent_nothing_is_written(): void
    {
        $before = $this->envText();
        Artisan::shouldReceive('call')->with('queue:restart')->once()->andThrow(new \RuntimeException('cache is down'));

        try {
            $this->instanceEnv()->apply(['OE_MODE' => 'staging'], 'tester');
            $this->fail('the failure must surface');
        } catch (\RuntimeException $e) {
            $this->assertSame('cache is down', $e->getMessage());
        }

        $this->assertSame($before, $this->envText());
        $this->assertSame([], (new InstanceState)->read()['env_history']);
    }

    // ---- this process sees the change too --------------------------------------------------------------

    #[Test]
    public function the_running_process_sees_the_new_url_mode_and_prefix_at_once(): void
    {
        Storage::fake('public');
        config(['queue.default' => 'sync']);

        $this->instanceEnv()->apply(['APP_URL' => 'https://new.example', 'OE_MODE' => 'staging', 'OE_INSTANCE_ID' => 'stg9', 'CACHE_PREFIX' => 'stg9_cache_', 'OE_MAIL_ALLOWLIST' => 'a@b.test'], 'tester');

        $this->assertSame('https://new.example', config('app.url'));
        $this->assertSame('https://new.example/storage', config('filesystems.disks.public.url'));
        $this->assertSame('staging', config('instance.mode'));
        $this->assertSame('stg9', config('instance.id'));
        $this->assertSame('stg9_cache_', config('cache.prefix'));
        $this->assertSame('a@b.test', config('instance.mail.allowlist'));
        $this->assertSame('https://new.example/storage/x.jpg', Storage::disk('public')->url('x.jpg'));
    }

    #[Test]
    public function a_prefix_change_makes_the_process_drop_the_stores_it_already_built(): void
    {
        config(['queue.default' => 'sync']);
        $manager = \Mockery::mock(CacheManager::class)->makePartial();
        $manager->shouldReceive('forgetDriver')->atLeast()->once();
        $this->app->instance('cache', $manager);

        $reloaded = (new ConfigReloader)->apply(['CACHE_PREFIX' => 'other_']);

        $this->assertContains('cache.prefix', $reloaded);
    }

    #[Test]
    public function a_url_only_change_does_not_touch_the_cache_stores(): void
    {
        $manager = \Mockery::mock(CacheManager::class)->makePartial();
        $manager->shouldNotReceive('forgetDriver');
        $this->app->instance('cache', $manager);

        (new ConfigReloader)->apply(['APP_URL' => 'https://another.example']);

        $this->addToAssertionCount(1);
    }

    // ---- the cache reset never flushes a shared store ---------------------------------------------------

    #[Test]
    public function the_cache_reset_clears_stores_of_its_own_and_never_touches_redis_or_memcached(): void
    {
        config(['cache.stores' => [
            'array' => ['driver' => 'array'],
            'redis' => ['driver' => 'redis', 'connection' => 'cache'],
            'memcached' => ['driver' => 'memcached'],
        ]]);

        $array = \Mockery::mock();
        $array->shouldReceive('flush')->once()->andReturn(true);
        Cache::shouldReceive('store')->once()->with('array')->andReturn($array);

        $report = (new InstanceCacheReset)->reset();

        $this->assertSame(['array'], $report['cleared']);
        $this->assertSame(['redis', 'memcached'], array_column($report['skipped'], 'store'));
        $this->assertStringContainsString('never flushed', $report['skipped'][0]['reason']);
    }
}
