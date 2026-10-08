<?php

namespace Tests\Feature;

use App\Enums\AdminNotificationCategory;
use App\Listeners\NotifyAdminsOnJobFailure;
use App\Models\Admin;
use App\Models\Order;
use App\Models\RefundRequest;
use App\Notifications\AdminDashboardNotification;
use App\Services\AdminNotificationService;
use Database\Seeders\RolesSeeder;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression coverage for a real, previously-invisible production bug
 * discovered during a full manual QA pass (2026-08-16): OrderObserver and
 * RefundRequestObserver both imported the non-existent class
 * `Filament\Notifications\Actions\Action` (the real one is
 * `Filament\Actions\Action`) — every single "New order placed" / "Refund
 * requested" admin bell notification had been silently failing to send
 * since these observers were written, because the resulting Error was
 * caught by a bare `catch (\Throwable $e) {}` with no logging. Zero test
 * coverage existed for either observer's notification behavior, which is
 * exactly how this went undetected.
 *
 * A second, independent bug in the same investigation: App\Notifications\
 * AdminDashboardNotification (fed by AdminNotificationService — job-
 * failure/health-check/cache alerts) produced data with no 'format' =>
 * 'filament' key, but Filament's own bell
 * (Filament\Notifications\Livewire\DatabaseNotifications::getNotificationsQuery())
 * filters strictly on `data->format = 'filament'` — so these alerts were
 * ALSO 100% invisible, with no other UI anywhere reading them either.
 */
class AdminNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    private function adminWithRole(string $role): Admin
    {
        $admin = Admin::factory()->create();
        $admin->assignRole($role);

        return $admin;
    }

    #[Test]
    public function creating_an_order_sends_a_real_filament_compatible_bell_notification(): void
    {
        $recipient = $this->adminWithRole('super_admin');

        $order = Order::factory()->create();

        $notification = DatabaseNotification::where('notifiable_id', $recipient->id)
            ->where('notifiable_type', Admin::class)
            ->where('data->title', 'New order placed')
            ->first();

        $this->assertNotNull($notification, 'OrderObserver did not create a "New order placed" notification — regression of the Filament\Actions\Action namespace bug.');
        $this->assertSame('filament', $notification->data['format']);
        $this->assertStringContainsString($order->order_number, $notification->data['body']);
        $this->assertNotEmpty($notification->data['actions']);
        $this->assertSame('view', $notification->data['actions'][0]['name']);
    }

    #[Test]
    public function action_urls_are_stored_host_relative_so_they_never_point_at_localhost(): void
    {
        $recipient = $this->adminWithRole('super_admin');

        app(AdminNotificationService::class)->create(
            $recipient,
            AdminNotificationCategory::System,
            'Cache health check failing',
            'detail',
            'http://localhost/admin/system/health-check-dashboard?tab=cache#top',
        );

        $notification = DatabaseNotification::where('notifiable_id', $recipient->id)->firstOrFail();

        $this->assertSame('/admin/system/health-check-dashboard?tab=cache#top', $notification->data['actions'][0]['url']);
        $this->assertSame('/admin/system/health-check-dashboard?tab=cache#top', $notification->data['action_url']);
    }

    #[Test]
    public function the_cleanup_migration_rewrites_stored_localhost_links_and_leaves_relative_ones_alone(): void
    {
        $recipient = $this->adminWithRole('super_admin');

        $row = fn (string $url) => [
            'id' => (string) Str::uuid(),
            'type' => AdminDashboardNotification::class,
            'notifiable_type' => Admin::class,
            'notifiable_id' => $recipient->id,
            'data' => json_encode(['format' => 'filament', 'action_url' => $url, 'actions' => [['name' => 'view', 'url' => $url]]]),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $stale = $row('http://localhost/admin/system/health-check-dashboard?tab=1');
        $fine = $row('/admin/system/failed-jobs');
        DB::table('notifications')->insert([$stale, $fine]);

        (require base_path('database/migrations/2026_10_08_000001_make_admin_notification_links_host_relative.php'))->up();

        $fixed = json_decode(DB::table('notifications')->where('id', $stale['id'])->value('data'), true);
        $this->assertSame('/admin/system/health-check-dashboard?tab=1', $fixed['action_url']);
        $this->assertSame('/admin/system/health-check-dashboard?tab=1', $fixed['actions'][0]['url']);

        $untouched = json_decode(DB::table('notifications')->where('id', $fine['id'])->value('data'), true);
        $this->assertSame('/admin/system/failed-jobs', $untouched['actions'][0]['url']);
    }

    #[Test]
    public function creating_a_refund_request_sends_a_real_filament_compatible_bell_notification(): void
    {
        $recipient = $this->adminWithRole('admin');
        $order = Order::factory()->create();

        RefundRequest::factory()->create(['order_id' => $order->id]);

        $notification = DatabaseNotification::where('notifiable_id', $recipient->id)
            ->where('notifiable_type', Admin::class)
            ->where('data->title', 'Refund requested')
            ->first();

        $this->assertNotNull($notification, 'RefundRequestObserver did not create a "Refund requested" notification — regression of the Filament\Actions\Action namespace bug.');
        $this->assertSame('filament', $notification->data['format']);
        $this->assertStringContainsString($order->order_number, $notification->data['body']);
    }

    #[Test]
    public function admin_dashboard_notification_produces_filament_compatible_data(): void
    {
        $admin = $this->adminWithRole('super_admin');

        app(AdminNotificationService::class)->create(
            $admin,
            AdminNotificationCategory::Inventory,
            'Low stock alert',
            'Only 2 units left of Test Part',
            '/admin/products/1',
        );

        $notification = DatabaseNotification::where('notifiable_id', $admin->id)->latest()->first();

        $this->assertNotNull($notification);
        $this->assertSame('filament', $notification->data['format'], 'AdminDashboardNotification must set format=filament or Filament\'s bell silently filters it out — see DatabaseNotifications::getNotificationsQuery().');
        $this->assertSame('Low stock alert', $notification->data['title']);
        $this->assertSame('Only 2 units left of Test Part', $notification->data['body']);
        $this->assertSame('inventory', $notification->data['category'], 'category must survive for AdminNotificationService::batchCheck()\'s JSON_EXTRACT query.');
        $this->assertNotEmpty($notification->data['actions']);
    }

    #[Test]
    public function five_or_more_same_category_alerts_within_a_minute_collapse_into_one_summary(): void
    {
        // AdminNotificationService::batchCheck() had zero coverage before
        // this (its JSON_UNQUOTE/JSON_EXTRACT query was raw MySQL syntax
        // that fatals on SQLite — fixed alongside this test to use
        // Laravel's driver-agnostic 'data->category' arrow syntax).
        $admin = $this->adminWithRole('super_admin');
        $service = app(AdminNotificationService::class);

        for ($i = 1; $i <= 5; $i++) {
            $service->create($admin, AdminNotificationCategory::System, "Alert {$i}", 'detail');
        }

        $notifications = DatabaseNotification::where('notifiable_id', $admin->id)->get();

        $this->assertCount(1, $notifications, 'Expected the 5 individual alerts to collapse into a single summary.');
        $this->assertStringContainsString('5 System alerts', $notifications->first()->data['title']);
    }

    #[Test]
    public function job_failure_notifications_are_also_filament_bell_visible(): void
    {
        $this->adminWithRole('super_admin');

        app(NotifyAdminsOnJobFailure::class)->handle(
            $this->fakeJobFailedEvent('App\\Jobs\\SendWelcomeEmail', 'Connection refused')
        );

        $notification = DatabaseNotification::where('data->title', 'Queue job failed: SendWelcomeEmail')->first();

        $this->assertNotNull($notification);
        $this->assertSame('filament', $notification->data['format']);
    }

    #[Test]
    public function job_failure_notifications_do_not_fire_for_the_notification_delivery_jobs_themselves(): void
    {
        // Regression guard for a real feedback loop found during QA: a
        // transient Windows-Docker-bind-mount log-write contention issue
        // made Filament's own DatabaseNotificationsSent broadcast job fail
        // repeatedly. Each failure spawned 5 "Queue job failed" notifications
        // (one per active admin), which is noisy but not itself a loop —
        // however if EITHER of these two job types is ever the one that
        // failed, notifying about it risks exactly this cascade, so both
        // are excluded regardless of root cause.
        $this->adminWithRole('super_admin');

        app(NotifyAdminsOnJobFailure::class)->handle(
            $this->fakeJobFailedEvent('Filament\\Notifications\\Events\\DatabaseNotificationsSent', 'Broadcasting failed')
        );
        app(NotifyAdminsOnJobFailure::class)->handle(
            $this->fakeJobFailedEvent('Illuminate\\Notifications\\SendQueuedNotifications', 'Broadcasting failed')
        );

        $this->assertSame(0, DatabaseNotification::count());
    }

    private function fakeJobFailedEvent(string $displayName, string $exceptionMessage): JobFailed
    {
        $job = new class($displayName) implements Job
        {
            public function __construct(private string $name) {}

            public function uuid()
            {
                return 'fake-uuid';
            }

            public function getJobId()
            {
                return 'fake-id';
            }

            public function resolveName()
            {
                return $this->name;
            }

            public function resolveQueuedJobClass()
            {
                return $this->name;
            }

            public function payload()
            {
                return ['displayName' => $this->name];
            }

            public function fire() {}

            public function release($delay = 0) {}

            public function isReleased()
            {
                return false;
            }

            public function delete() {}

            public function isDeleted()
            {
                return false;
            }

            public function isDeletedOrReleased()
            {
                return false;
            }

            public function attempts()
            {
                return 1;
            }

            public function markAsFailed() {}

            public function fail($e = null) {}

            public function maxTries()
            {
                return null;
            }

            public function maxExceptions()
            {
                return null;
            }

            public function timeout()
            {
                return null;
            }

            public function retryUntil()
            {
                return null;
            }

            public function getRawBody()
            {
                return '';
            }

            public function getConnectionName()
            {
                return 'redis';
            }

            public function getQueue()
            {
                return 'default';
            }

            public function getName()
            {
                return $this->name;
            }

            public function hasFailed()
            {
                return true;
            }
        };

        return new JobFailed('redis', $job, new \Exception($exceptionMessage));
    }
}
