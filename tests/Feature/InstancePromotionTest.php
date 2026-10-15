<?php

namespace Tests\Feature;

use App\Services\Install\InstallManager;
use App\Services\Instance\ActivityDiscarder;
use App\Services\Instance\ConfigReloader;
use App\Services\Instance\InstanceEnv;
use App\Services\Instance\InstanceMode;
use App\Services\Instance\InstancePromoter;
use App\Services\Instance\InstanceState;
use App\Services\Instance\PromotionBlockedException;
use App\Services\Instance\Readiness\InstanceQueueProbe;
use App\Services\Instance\Readiness\ReadinessCheck;
use App\Services\Instance\Readiness\ReadinessContext;
use App\Services\Instance\Readiness\ReadinessProbe;
use App\Services\Instance\Readiness\ReadinessRegistry;
use App\Services\Instance\Readiness\ReadinessRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Support\PlantsRows;
use Tests\TestCase;

/**
 * Phase 7.5: leaving quarantine and going live: the discard of what a staging copy created, the checks that gate each way out,
 * the waivers, and the proof that the queue workers followed the mode.
 */
class InstancePromotionTest extends TestCase
{
    use PlantsRows;
    use RefreshDatabase;

    private string $dir = '';

    private string $ledgerBackup = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/oe-promo-'.getmypid().'-'.uniqid();
        mkdir($this->dir, 0775, true);

        $ledger = InstanceState::file()->path();
        $this->ledgerBackup = is_file($ledger) ? (string) file_get_contents($ledger) : '';
        @unlink($ledger);
        @unlink($ledger.'.prev');

        config(['app.url' => 'https://shop.example', 'queue.default' => 'sync', 'instance.id' => 'iabc123', 'instance.mode' => 'quarantine']);
        Queue::fake();
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

    // ---- fixtures ---------------------------------------------------------------------------------------------

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

        $this->app->instance(ReadinessRunner::class, new ReadinessRunner(new ReadinessRegistry($probes === [] ? [$this->probe('fine', 'pass')] : $probes), $this->dir.'/readiness.json'));

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

                // A worker that follows the file answers with what .env says now; one told what to say answers with that.
                return array_replace(['nonce' => 'n', 'at' => now()->toIso8601String(), 'pid' => 1, 'instance_id' => 'iabc123', 'queue' => 'default', 'connection' => 'database'], $this->answer);
            }
        });
    }

    private function probe(string $key, string $status, bool $overridable = false, string $group = 'runtime'): ReadinessProbe
    {
        return new ReadinessProbe($key, $group, fn (ReadinessContext $c): ReadinessCheck => new ReadinessCheck($key, $key, $group, $status, '', [], '', $overridable), ReadinessProbe::LIVE);
    }

    private function promoter(): InstancePromoter
    {
        return app(InstancePromoter::class);
    }

    private function envText(): string
    {
        return (string) file_get_contents($this->dir.'/.env');
    }

    private function ledger(): array
    {
        return (new InstanceState)->read();
    }

    private function blocked(callable $do): PromotionBlockedException
    {
        try {
            $do();
        } catch (PromotionBlockedException $e) {
            return $e;
        }

        $this->fail('expected the launch to be blocked');
    }

    /** Watermarks at the current top of each table, the way the import records them. */
    private function markTheCopy(): void
    {
        $marks = [];
        foreach (ActivityDiscarder::WATCHED as $table) {
            $marks[$table] = (int) (DB::table($table)->max('id') ?? 0);
        }
        (new InstanceState)->setWatermarks($marks);
    }

    // ---- what the copy created --------------------------------------------------------------------------------------------

    #[Test]
    public function rows_above_the_watermarks_are_found_and_only_those_are_deleted_children_first(): void
    {
        $this->plantRow('contact_messages', []);
        $oldOrder = $this->plantRow('orders', ['order_number' => 'ORD-OLD']);
        $oldPayment = $this->plantRow('payments', ['order_id' => $oldOrder]);
        $this->markTheCopy();

        // Activity on the copy: a customer, an order with a payment and an item, a message.
        $newOrder = $this->plantRow('orders', ['order_number' => 'ORD-NEW']);
        $payment = $this->plantRow('payments', ['order_id' => $newOrder]);
        $this->plantRow('contact_messages', []);
        $discarder = new ActivityDiscarder(new InstanceState);

        $this->assertSame(['orders' => 1, 'payments' => 1, 'contact_messages' => 1], array_intersect_key($discarder->activity(), array_flip(['orders', 'payments', 'contact_messages'])));

        $result = $discarder->discard();

        $this->assertSame(1, $result['deleted']['orders']);
        $this->assertSame(0, DB::table('orders')->where('id', $newOrder)->count());
        $this->assertSame(1, DB::table('orders')->where('id', $oldOrder)->count(), 'what came with the import stays');
        $this->assertSame(0, DB::table('payments')->where('id', $payment)->count());
        $this->assertSame(1, DB::table('payments')->where('id', $oldPayment)->count());
        $this->assertSame([], $discarder->activity());

        $this->assertSame([], (new ActivityDiscarder(new InstanceState))->discard()['deleted'], 'discarding again deletes nothing more');
    }

    #[Test]
    public function a_customer_who_was_soft_deleted_on_the_copy_is_really_removed(): void
    {
        $this->markTheCopy();
        $id = $this->plantRow('users', ['name' => 'Test Customer', 'email' => 'test@example.test', 'deleted_at' => now()]);

        $discarder = new ActivityDiscarder(new InstanceState);

        $this->assertSame(1, $discarder->activity()['users']);
        $discarder->discard();
        $this->assertSame(0, DB::table('users')->where('id', $id)->count(), 'a soft delete would have kept the row and everything hanging on it');
    }

    #[Test]
    public function the_order_and_invoice_numbers_go_back_to_where_they_stood_so_the_real_shop_has_no_gap(): void
    {
        DB::table('sequences')->updateOrInsert(['type' => 'order'], ['current_value' => 7, 'resets_monthly' => 1, 'last_reset_month' => now()->format('Y-m'), 'created_at' => now(), 'updated_at' => now()]);
        $this->markTheCopy();
        (new InstanceState)->update(function (array $ledger): array {
            $ledger['sequence_baseline'] = ['series' => ['order' => ['value' => 7, 'last_reset_month' => now()->format('Y-m'), 'raised_from' => null]]];

            return $ledger;
        });

        DB::table('sequences')->where('type', 'order')->update(['current_value' => 31]); // test orders on the copy used up 24 numbers

        $result = (new ActivityDiscarder(new InstanceState))->discard();

        $this->assertSame(['order'], $result['counters_restored']);
        $this->assertSame(7, (int) DB::table('sequences')->where('type', 'order')->value('current_value'));
    }

    // ---- leaving quarantine -----------------------------------------------------------------------------------------------

    #[Test]
    public function only_a_site_in_quarantine_can_be_released_and_never_back_into_quarantine(): void
    {
        $this->arrange('production');
        $e = $this->blocked(fn () => $this->promoter()->release(InstanceMode::Staging));
        $this->assertSame('not_quarantine', $e->reason);

        $this->arrange('quarantine');
        $e = $this->blocked(fn () => $this->promoter()->release(InstanceMode::Quarantine));
        $this->assertSame('not_quarantine', $e->reason);
    }

    #[Test]
    public function a_failing_check_blocks_the_release_and_names_it_and_nothing_changes(): void
    {
        $this->arrange('quarantine', [$this->probe('rows_vs_manifest', 'fail', false, 'data'), $this->probe('fine', 'pass')]);

        $e = $this->blocked(fn () => $this->promoter()->release(InstanceMode::Production));

        $this->assertSame('readiness_blocked', $e->reason);
        $this->assertSame('rows_vs_manifest', $e->details['checks'][0]['key']);
        $this->assertStringContainsString('OE_MODE=quarantine', $this->envText());
    }

    #[Test]
    public function a_pending_check_blocks_too_because_not_knowing_is_not_a_yes(): void
    {
        $this->arrange('quarantine', [$this->probe('scheduler_heartbeat', 'pending')]);

        $this->assertSame('readiness_blocked', $this->blocked(fn () => $this->promoter()->release(InstanceMode::Production))->reason);
    }

    #[Test]
    public function a_clear_site_is_released_the_workers_are_told_first_and_the_watermarks_go_for_a_live_launch(): void
    {
        $this->arrange('quarantine', [$this->probe('fine', 'pass')], ['mode' => 'production'], workers: true);
        $this->markTheCopy();
        $this->assertNotEmpty($this->ledger()['watermarks']);

        $result = $this->promoter()->release(InstanceMode::Production, by: 'test');

        $this->assertSame(['from' => 'quarantine', 'to' => 'production', 'worker' => 'confirmed', 'waived' => []], $result);
        $this->assertStringContainsString('OE_MODE="production"', $this->envText());
        $this->assertSame([], $this->ledger()['watermarks'], 'a live launch has no copy activity to tell apart');
        $this->assertSame('production', (new InstanceState)->recordedMode());
    }

    #[Test]
    public function releasing_to_staging_keeps_the_watermarks_for_the_day_it_goes_live(): void
    {
        $this->arrange('quarantine');
        $this->markTheCopy();

        $this->promoter()->release(InstanceMode::Staging, by: 'test');

        $this->assertStringContainsString('OE_MODE="staging"', $this->envText());
        $this->assertArrayHasKey('orders', $this->ledger()['watermarks']);
    }

    #[Test]
    public function a_worker_that_keeps_the_old_mode_undoes_the_release(): void
    {
        $this->arrange('quarantine', [$this->probe('fine', 'pass')], ['mode' => 'quarantine'], workers: true);

        $e = $this->blocked(fn () => $this->promoter()->release(InstanceMode::Production, by: 'test'));

        $this->assertSame('workers_stale', $e->reason);
        $this->assertStringContainsString('OE_MODE="quarantine"', $this->envText(), 'the site is as it was');
    }

    // ---- waivers --------------------------------------------------------------------------------------------------------

    #[Test]
    public function an_overridable_failure_can_be_waived_with_a_reason_that_is_kept(): void
    {
        $this->arrange('quarantine', [$this->probe('storage_link', 'fail', true), $this->probe('fine', 'pass')]);

        $result = $this->promoter()->release(InstanceMode::Staging, ['storage_link' => 'the link is made by the host panel after launch'], 'test');

        $this->assertSame(['storage_link'], $result['waived']);
        $waiver = $this->ledger()['waivers'][0];
        $this->assertSame('storage_link', $waiver['key']);
        $this->assertSame('the link is made by the host panel after launch', $waiver['reason']);
        $this->assertSame('test', $waiver['by']);
    }

    #[Test]
    public function a_failure_that_may_not_be_waived_a_short_reason_and_an_unknown_check_are_all_refused(): void
    {
        $this->arrange('quarantine', [$this->probe('queue_worker_mode', 'fail', false), $this->probe('storage_link', 'fail', true)]);

        foreach ([
            ['queue_worker_mode' => 'we will restart it later, promise'],
            ['storage_link' => 'later'],
            ['no_such_check' => 'a perfectly long reason here'],
        ] as $waivers) {
            $this->assertSame('waiver_refused', $this->blocked(fn () => $this->promoter()->release(InstanceMode::Staging, $waivers))->reason);
        }

        $this->assertSame([], $this->ledger()['waivers'] ?? [], 'a refused waiver is not recorded');
    }

    // ---- going live -------------------------------------------------------------------------------------------------------

    #[Test]
    public function only_a_staging_copy_can_go_live_and_only_when_its_host_is_typed(): void
    {
        $this->arrange('quarantine');
        $this->assertSame('not_staging', $this->blocked(fn () => $this->promoter()->goLive('shop.example'))->reason);

        $this->arrange('staging');
        $e = $this->blocked(fn () => $this->promoter()->goLive('typo.example'));
        $this->assertSame('host_not_typed', $e->reason);
        $this->assertSame('shop.example', $e->details['expected']);
        $this->assertStringContainsString('OE_MODE=staging', $this->envText());
    }

    #[Test]
    public function activity_on_the_copy_blocks_going_live_unless_it_is_discarded(): void
    {
        $this->arrange('staging');
        $this->markTheCopy();
        $this->plantRow('contact_messages', []);

        $e = $this->blocked(fn () => $this->promoter()->goLive('shop.example'));

        $this->assertSame('activity_present', $e->reason);
        $this->assertSame(['contact_messages' => 1], $e->details['activity']);
        $this->assertSame(1, DB::table('contact_messages')->count(), 'nothing was deleted by the refusal');

        $result = $this->promoter()->goLive('shop.example', ['discard_activity' => true], 'test');

        $this->assertSame(['contact_messages' => 1], $result['discarded']);
        $this->assertSame(0, DB::table('contact_messages')->count());
        $this->assertStringContainsString('OE_MODE="production"', $this->envText());
        $this->assertSame([], $this->ledger()['watermarks']);
    }

    #[Test]
    public function a_refused_go_live_never_discards_the_staging_activity(): void
    {
        $this->arrange('staging', [$this->probe('blocker', ReadinessCheck::FAIL)]);
        $this->markTheCopy();
        $this->plantRow('contact_messages', []);

        $this->assertSame('readiness_blocked', $this->blocked(fn () => $this->promoter()->goLive('shop.example', ['discard_activity' => true]))->reason);
        $this->assertSame(1, DB::table('contact_messages')->count(), 'a blocked launch keeps the activity');

        DB::table('settings')->updateOrInsert(['group' => 'payment', 'key' => 'airwallex_environment'], ['value' => 'live', 'type' => 'string', 'is_encrypted' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame('payments_unconfirmed', $this->blocked(fn () => $this->promoter()->goLive('shop.example', ['discard_activity' => true]))->reason);
        $this->assertSame(1, DB::table('contact_messages')->count(), 'an unconfirmed payments box keeps the activity');
    }

    #[Test]
    public function real_card_payments_have_to_be_confirmed_on_purpose(): void
    {
        $this->arrange('staging');
        DB::table('settings')->updateOrInsert(['group' => 'payment', 'key' => 'airwallex_environment'], ['value' => 'live', 'type' => 'string', 'is_encrypted' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame('payments_unconfirmed', $this->blocked(fn () => $this->promoter()->goLive('shop.example'))->reason);

        $this->promoter()->goLive('shop.example', ['confirm_payments' => true], 'test');
        $this->assertStringContainsString('OE_MODE="production"', $this->envText());
    }

    #[Test]
    public function going_live_puts_the_canonical_host_back_by_the_live_launch_rule_and_the_launch_checks_still_apply(): void
    {
        $this->arrange('staging', [$this->probe('runtime_thing', 'fail', false)]);
        $this->assertSame('readiness_blocked', $this->blocked(fn () => $this->promoter()->goLive('shop.example'))->reason);

        $this->arrange('staging');
        (new InstanceState)->update(function (array $ledger): array {
            $ledger['import'] = ['source_canonical_host' => 'www.old.example'];

            return $ledger;
        });
        DB::table('settings')->updateOrInsert(['group' => 'seo', 'key' => 'canonical_host'], ['value' => '', 'type' => 'string', 'is_encrypted' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $result = $this->promoter()->goLive('shop.example', [], 'test');

        $this->assertSame('shop.example', $result['canonical_host']);
        $this->assertSame('shop.example', DB::table('settings')->where('group', 'seo')->where('key', 'canonical_host')->value('value'));
    }

    #[Test]
    public function a_worker_that_kept_the_staging_mode_stops_the_launch_and_the_copy_stays_a_copy(): void
    {
        $this->arrange('staging', [$this->probe('fine', 'pass')], ['mode' => 'staging'], workers: true);

        $e = $this->blocked(fn () => $this->promoter()->goLive('shop.example', [], 'test'));

        $this->assertSame('workers_stale', $e->reason);
        $this->assertStringContainsString('OE_MODE="staging"', $this->envText());
    }

    // ---- the commands -------------------------------------------------------------------------------------------------------

    #[Test]
    public function the_release_command_exits_with_three_when_something_blocks_it_and_two_for_wrong_options(): void
    {
        $this->arrange('quarantine', [$this->probe('rows_vs_manifest', 'fail', false, 'data')]);

        $this->assertSame(3, Artisan::call('oeparts:instance:release', ['--to' => 'production']));
        $this->assertSame(2, Artisan::call('oeparts:instance:release', ['--to' => 'nonsense']));
        $this->assertSame(2, Artisan::call('oeparts:instance:release', ['--to' => 'production', '--waive' => ['no-reason-here']]));
    }

    #[Test]
    public function the_release_command_releases_a_clear_site_and_can_say_so_as_json(): void
    {
        $this->arrange('quarantine');

        // The mode change reloads the configuration (a nested artisan call): read THIS command's own output, not the last one's.
        $buffer = new BufferedOutput;
        $this->assertSame(0, Artisan::call('oeparts:instance:release', ['--to' => 'staging', '--json' => true], $buffer));
        $output = $buffer->fetch();
        $json = json_decode($output, true);

        $this->assertIsArray($json, $output);
        $this->assertTrue($json['released']);
        $this->assertSame('staging', $json['to']);
    }

    #[Test]
    public function the_go_live_command_needs_the_host_typed_out(): void
    {
        $this->arrange('staging');

        $this->assertSame(2, Artisan::call('oeparts:instance:go-live'), 'no host typed');
        $this->assertSame(3, Artisan::call('oeparts:instance:go-live', ['--host' => 'wrong.example']));
        $this->assertSame(0, Artisan::call('oeparts:instance:go-live', ['--host' => 'shop.example']));
        $this->assertStringContainsString('OE_MODE="production"', $this->envText());
    }
}
