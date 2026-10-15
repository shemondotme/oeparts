<?php

namespace Tests\Feature\Instance\Concerns;

use App\Filament\Pages\System\InstanceReadiness;
use App\Models\Admin;
use App\Services\Install\InstallManager;
use App\Services\Instance\ActivityDiscarder;
use App\Services\Instance\ConfigReloader;
use App\Services\Instance\InstanceEnv;
use App\Services\Instance\InstanceMode;
use App\Services\Instance\InstanceModeSwitcher;
use App\Services\Instance\InstanceState;
use App\Services\Instance\Readiness\InstanceQueueProbe;
use App\Services\Instance\Readiness\ReadinessCheck;
use App\Services\Instance\Readiness\ReadinessContext;
use App\Services\Instance\Readiness\ReadinessProbe;
use App\Services\Instance\Readiness\ReadinessRegistry;
use App\Services\Instance\Readiness\ReadinessRunner;
use Database\Seeders\RolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;

/**
 * What the Site mode tests share: a super admin, a throw-away .env and readiness report, a readiness list the test controls and a queue worker that answers as told.
 * Everything the services write lives in a temp directory; the ledger is the one real file the instance code owns (storage/app/instance/instance.json), so it is saved and put back like InstancePromotionTest does.
 */
trait ArrangesInstanceSite
{
    private string $dir = '';

    private string $ledgerBackup = '';

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->dir = sys_get_temp_dir().'/oe-site-mode-'.getmypid().'-'.uniqid();
        mkdir($this->dir, 0775, true);

        $ledger = InstanceState::file()->path();
        $this->ledgerBackup = is_file($ledger) ? (string) file_get_contents($ledger) : '';
        @unlink($ledger);
        @unlink($ledger.'.prev');

        config(['app.url' => 'https://shop.example', 'queue.default' => 'sync', 'instance.id' => 'iabc123', 'instance.mail.allowlist' => null]);
        Queue::fake();

        $this->admin = $this->makeAdmin();
        $this->actingAs($this->admin, 'admin');
    }

    protected function tearDown(): void
    {
        $ledger = InstanceState::file()->path();
        @unlink($ledger);
        @unlink($ledger.'.prev');

        if ($this->ledgerBackup !== '') {
            file_put_contents($ledger, $this->ledgerBackup);
        }

        RateLimiter::clear('reauth:'.$this->admin->getKey().'|127.0.0.1');
        exec('rm -rf '.escapeshellarg($this->dir));

        parent::tearDown();
    }

    // ---- fixtures ---------------------------------------------------------------------------------------------------------

    private function makeAdmin(string $role = 'super_admin'): Admin
    {
        $admin = Admin::factory()->create(['is_active' => true, 'password' => Hash::make('correct-horse')]);
        $admin->assignRole($role);
        RateLimiter::clear('reauth:'.$admin->getKey().'|127.0.0.1');

        return $admin;
    }

    /** The installation's .env, a readiness list we control and (optionally) a worker that answers as told. */
    private function arrange(string $mode, array $probes = [], ?array $workerAnswer = null, bool $workers = false): void
    {
        config(['instance.mode' => $mode]);

        $path = $this->dir.'/.env';
        file_put_contents($path, "APP_NAME=OeParts\nOE_MODE={$mode}\nOE_INSTANCE_ID=iabc123\n");
        $installer = new class($path) extends InstallManager
        {
            public function __construct(private readonly string $path) {}

            protected function envPath(): string
            {
                return $this->path;
            }
        };
        $this->app->instance(InstanceEnv::class, new InstanceEnv($installer, new ConfigReloader, new InstanceState, $path));

        $registry = new ReadinessRegistry($probes === [] ? [$this->probe('fine', 'pass')] : $probes);
        $this->app->instance(ReadinessRegistry::class, $registry);
        $this->app->instance(ReadinessRunner::class, new ReadinessRunner($registry, $this->dir.'/readiness.json'));

        if ($workers) {
            config(['queue.default' => 'database']);
        }

        $this->app->instance(InstanceQueueProbe::class, new class($workerAnswer, $workers) extends InstanceQueueProbe
        {
            public function __construct(private readonly ?array $answer, private readonly bool $on) {}

            public function available(): bool
            {
                return $this->on;
            }

            public function ask(?int $waitSeconds = null): ?array
            {
                if ($this->answer === null) {
                    return null;
                }

                return array_replace(['nonce' => 'n', 'at' => now()->toIso8601String(), 'pid' => 1, 'instance_id' => 'iabc123', 'queue' => 'default', 'connection' => 'database'], $this->answer);
            }
        });
    }

    private function probe(string $key, string $status, bool $overridable = false, string $group = 'runtime', string $message = '', string $fix = ''): ReadinessProbe
    {
        return new ReadinessProbe(
            $key, $group,
            fn (ReadinessContext $c): ReadinessCheck => new ReadinessCheck($key, 'Label of '.$key, $group, $status, $message, [], $fix, $overridable),
            ReadinessProbe::LIVE,
        );
    }

    private function envText(): string
    {
        return (string) file_get_contents($this->dir.'/.env');
    }

    private function ledger(): array
    {
        return (new InstanceState)->read();
    }

    /** Watermarks at the current top of each watched table, the way the import records them. */
    private function markTheCopy(): void
    {
        $marks = [];
        foreach (ActivityDiscarder::WATCHED as $table) {
            $marks[$table] = (int) (DB::table($table)->max('id') ?? 0);
        }
        (new InstanceState)->setWatermarks($marks);
    }

    /** The page with a complete report already on file (the checks are run through the page itself). */
    private function pageAfterChecks(): Testable
    {
        return Livewire::test(InstanceReadiness::class)->callAction('runChecks')->call('advanceChecks')->assertSet('checking', false);
    }

    /**
     * A mode switcher whose queue worker never follows the first change (it answers with the old mode), and that records what it
     * was asked. The real switcher waits half a minute before it gives up; InstancePromotionTest covers that with the real one.
     */
    private function stubbornWorkerSwitcher(): InstanceModeSwitcher
    {
        $switcher = new class extends InstanceModeSwitcher
        {
            /** @var list<string> */
            public array $asked = [];

            public function __construct() {}

            public function switch(InstanceMode $to, string $by, int $waitSeconds = 30): array
            {
                $this->asked[] = $to->value;

                return ['from' => 'x', 'to' => $to->value, 'wrote' => true, 'worker' => count($this->asked) === 1 ? self::WORKER_STALE : self::WORKER_CONFIRMED];
            }
        };
        $this->app->instance(InstanceModeSwitcher::class, $switcher);

        return $switcher;
    }
}
