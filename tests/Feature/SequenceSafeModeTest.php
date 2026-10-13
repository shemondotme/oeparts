<?php

namespace Tests\Feature;

use App\Enums\SequenceType;
use App\Jobs\SendAdminWebPush;
use App\Models\Admin;
use App\Models\AdminPushSubscription;
use App\Models\BackupRun;
use App\Models\Sequence;
use App\Models\Setting;
use App\Services\Backup\BackupLock;
use App\Services\Backup\BackupManager;
use App\Services\Backup\Exceptions\BackupException;
use App\Services\Instance\InstanceModeBlockedException;
use App\Services\Instance\SafeMode;
use App\Services\Push\WebPushClient;
use App\Services\SequenceService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 4.6: three small things that keep a copy from colliding with the real shop. Its order and invoice numbers
 * are visibly test numbers, it refuses to write backups to a disk other sites may share (run ids restart at 1 on a
 * copy, so a shared bucket would be overwritten), and it can never send a browser push.
 */
class SequenceSafeModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SafeMode::resetLogMemory();

        foreach ([SequenceType::Order, SequenceType::Invoice] as $type) {
            Sequence::create(['type' => $type, 'current_value' => 0, 'resets_monthly' => true, 'last_reset_month' => now()->format('Y-m')]);
        }
        Sequence::create(['type' => SequenceType::Rma, 'current_value' => 0, 'resets_monthly' => false, 'last_reset_month' => null]);
    }

    private function mode(string $mode): void
    {
        config(['instance.mode' => $mode]);
    }

    // ---- numbers ----------------------------------------------------------------------------------------------

    #[Test]
    public function production_numbers_are_exactly_what_they_always_were(): void
    {
        $this->mode('production');
        $month = now()->format('Ym');
        $service = new SequenceService;

        $this->assertSame("ORD-{$month}-000001", $service->nextOrderNumber());
        $this->assertSame("INV-{$month}-000001", $service->nextInvoiceNumber());
        $this->assertSame('RMA-000001', $service->nextRmaNumber());
    }

    #[Test]
    #[DataProvider('copyModes')]
    public function a_copy_numbers_orders_invoices_and_returns_with_a_test_prefix(string $mode): void
    {
        $this->mode($mode);
        $month = now()->format('Ym');
        $service = new SequenceService;

        $this->assertSame("T-ORD-{$month}-000001", $service->nextOrderNumber());
        $this->assertSame("T-INV-{$month}-000001", $service->nextInvoiceNumber());
        $this->assertSame('T-RMA-000001', $service->nextRmaNumber());
    }

    /** @return array<string, array{0: string}> */
    public static function copyModes(): array
    {
        return ['staging' => ['staging'], 'quarantine' => ['quarantine'], 'typo' => ['stagging']];
    }

    #[Test]
    public function a_copy_can_never_produce_a_number_the_real_shop_already_used(): void
    {
        $month = now()->format('Ym');
        $this->mode('production');
        $real = (new SequenceService)->nextOrderNumber();

        Sequence::where('type', SequenceType::Order)->update(['current_value' => 0]);
        $this->mode('staging');
        $test = (new SequenceService)->nextOrderNumber();

        $this->assertSame("ORD-{$month}-000001", $real);
        $this->assertNotSame($real, $test, 'the same counter value gives a different, prefixed number on a copy');
    }

    #[Test]
    public function the_prefix_is_never_longer_than_thirty_characters(): void
    {
        config(['instance.mode' => 'staging']);
        Setting::updateOrCreate(['group' => 'orders', 'key' => 'order_number_prefix'], ['value' => str_repeat('X', 80), 'type' => 'string', 'is_encrypted' => false]);
        app(SettingsService::class)->forget('orders');

        $number = (new SequenceService)->nextOrderNumber();
        $prefix = explode('-'.now()->format('Ym').'-', $number)[0];

        $this->assertLessThanOrEqual(30, strlen($prefix));
        $this->assertStringStartsWith('T-XXX', $prefix);
    }

    // ---- backups ------------------------------------------------------------------------------------------------------

    private function remoteBackupDisk(): void
    {
        config(['filesystems.disks.offsite' => ['driver' => 's3', 'key' => 'k', 'secret' => 's', 'region' => 'eu-west-1', 'bucket' => 'b'], 'backup.disk' => 'offsite']);
    }

    #[Test]
    #[DataProvider('copyModes')]
    public function a_copy_refuses_to_start_a_backup_to_a_remote_disk_before_creating_anything(string $mode): void
    {
        $this->remoteBackupDisk();
        $this->mode($mode);
        $before = BackupRun::count();

        try {
            app(BackupManager::class)->start();
            $this->fail('a copy must not back up to a shared disk');
        } catch (BackupException $e) {
            $this->assertStringContainsString('remote', $e->getMessage());
        }

        $this->assertSame($before, BackupRun::count(), 'no run row was created');
    }

    #[Test]
    public function a_copy_may_back_up_to_a_local_disk(): void
    {
        config(['backup.disk' => 'local']);
        $this->mode('staging');

        $run = app(BackupManager::class)->start();

        $this->assertSame('local', $run->disk);
        app(BackupLock::class)->release();
    }

    #[Test]
    public function the_real_shop_may_still_back_up_to_a_remote_disk(): void
    {
        $this->remoteBackupDisk();
        $this->mode('production');

        try {
            $run = app(BackupManager::class)->start();
            $this->assertSame('offsite', $run->disk);
            app(BackupLock::class)->release();
        } catch (BackupException $e) {
            $this->assertStringNotContainsString('remote disk', $e->getMessage(), 'a different reason (no real bucket) is fine; the copy rule must not fire');
        }
    }

    // ---- web push -----------------------------------------------------------------------------------------------------

    #[Test]
    public function the_push_client_itself_refuses_on_a_copy(): void
    {
        $this->mode('staging');

        $this->expectException(InstanceModeBlockedException::class);
        app(WebPushClient::class)->send(new Collection, ['title' => 'x'], false);
    }

    #[Test]
    public function a_push_job_on_a_copy_does_nothing_and_leaves_every_subscription_alone(): void
    {
        $admin = Admin::factory()->create();
        $subscription = AdminPushSubscription::create([
            'admin_id' => $admin->id, 'endpoint' => 'https://push.example/abc', 'endpoint_hash' => hash('sha256', 'https://push.example/abc'), 'public_key' => 'pk', 'auth_token' => 'at', 'content_encoding' => 'aes128gcm',
        ]);
        $client = new class extends WebPushClient
        {
            public int $calls = 0;

            public function __construct() {}

            public function send(Collection $subscriptions, array $payload, bool $urgent): array
            {
                $this->calls++;

                return [];
            }
        };

        $this->mode('staging');
        (new SendAdminWebPush($admin->id, ['title' => 'x']))->handle($client);

        $this->assertSame(0, $client->calls);
        $fresh = $subscription->fresh();
        $this->assertNotNull($fresh, 'the subscription was not deleted');
        $this->assertSame(0, (int) $fresh->failure_count, 'and no failure was counted against it');
    }
}
