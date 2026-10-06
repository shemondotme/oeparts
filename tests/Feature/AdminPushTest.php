<?php

namespace Tests\Feature;

use App\Console\Commands\AdminPushFlushDeferred;
use App\Enums\AdminNotificationCategory;
use App\Filament\Pages\System\AlertControlCenter;
use App\Filament\Pages\System\AlertPreferences;
use App\Jobs\SendAdminWebPush;
use App\Listeners\NotifyAdminsOnJobFailure;
use App\Models\Admin;
use App\Models\AdminPushDeferred;
use App\Models\AdminPushDelivery;
use App\Models\AdminPushPreference;
use App\Models\AdminPushSubscription;
use App\Models\PushTopic;
use App\Notifications\ContactMessageNotification;
use App\Services\AdminNotificationService;
use App\Services\Push\AdminPushService;
use App\Services\Push\PushTopicRegistry;
use App\Services\Push\VapidKeys;
use App\Services\Push\WebPushClient;
use App\Services\SettingsService;
use App\Support\AdminNotifier;
use Carbon\Carbon;
use Database\Seeders\RolesSeeder;
use Filament\Notifications\Notification;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Admin Web Push: device registration, the single NotificationSent choke point,
 * every control layer (site -> topic -> role -> admin -> quiet hours), delivery
 * bookkeeping, the signed mark-as-read link and the two Filament pages.
 *
 * The real push services are never contacted: WebPushClient is replaced by a
 * fake wherever a delivery would happen.
 */
class AdminPushTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    private function admin(string $role = 'super_admin', bool $withDevice = true): Admin
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole($role);

        if ($withDevice) {
            $this->device($admin);
        }

        return $admin;
    }

    private function device(Admin $admin, ?string $endpoint = null): AdminPushSubscription
    {
        $endpoint ??= 'https://push.example.test/'.uniqid();

        return AdminPushSubscription::create([
            'admin_id' => $admin->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => AdminPushSubscription::hashEndpoint($endpoint),
            'public_key' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM',
            'auth_token' => 'tBHItJI5svbpez7KI4CCXg',
            'content_encoding' => 'aes128gcm',
            'device_label' => 'Chrome · Windows',
        ]);
    }

    private function sendOrderBell(): void
    {
        AdminNotifier::toRoles(['*'], Notification::make()
            ->title('New order placed')
            ->body('ORD-1 · €120.00')
            ->viewData(['push_topic' => 'new_order']));
    }

    // ── Registration API ────────────────────────────────────────────────

    #[Test]
    public function a_signed_in_admin_can_register_and_remove_a_device(): void
    {
        $admin = $this->admin(withDevice: false);
        $payload = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
            'keys' => ['p256dh' => 'BKey', 'auth' => 'authkey'],
            'contentEncoding' => 'aes128gcm',
        ];

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.push.subscribe'), $payload, ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0'])
            ->assertOk();

        $this->assertDatabaseHas('admin_push_subscriptions', ['admin_id' => $admin->id, 'device_label' => 'Chrome · Windows']);

        // Re-registering the same endpoint is idempotent, not a duplicate.
        $this->actingAs($admin, 'admin')->postJson(route('admin.push.subscribe'), $payload)->assertOk();
        $this->assertSame(1, AdminPushSubscription::count());

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.push.unsubscribe'), ['endpoint' => $payload['endpoint']])
            ->assertOk();

        $this->assertSame(0, AdminPushSubscription::count());
    }

    #[Test]
    public function registering_requires_an_https_endpoint_and_a_signed_in_admin(): void
    {
        $this->postJson(route('admin.push.subscribe'), [])->assertRedirect(route('filament.admin.auth.login'));
    }

    #[Test]
    public function a_browser_taken_over_by_another_admin_changes_owner(): void
    {
        $first = $this->admin(withDevice: false);
        $second = $this->admin('manager', withDevice: false);
        $payload = ['endpoint' => 'https://push.example.test/shared', 'keys' => ['p256dh' => 'k', 'auth' => 'a']];

        $this->actingAs($first, 'admin')->postJson(route('admin.push.subscribe'), $payload)->assertOk();
        $this->actingAs($second, 'admin')->postJson(route('admin.push.subscribe'), $payload)->assertOk();

        $this->assertSame(1, AdminPushSubscription::count());
        $this->assertSame($second->id, AdminPushSubscription::first()->admin_id);
    }

    #[Test]
    public function the_manifest_and_service_worker_are_served_with_the_right_scope(): void
    {
        $this->getJson(route('admin.push.manifest'))
            ->assertOk()
            ->assertJsonPath('scope', '/admin')
            ->assertJsonPath('start_url', '/admin')
            ->assertJsonPath('display', 'standalone');

        $this->get(url('/admin/sw.js'))
            ->assertOk()
            ->assertHeader('Service-Worker-Allowed', '/admin')
            ->assertSee('addEventListener(\'push\'', false);
    }

    // ── The NotificationSent choke point ────────────────────────────────

    #[Test]
    public function a_bell_notification_is_pushed_to_the_admins_device(): void
    {
        Bus::fake([SendAdminWebPush::class]);
        $admin = $this->admin();

        $this->sendOrderBell();

        Bus::assertDispatched(SendAdminWebPush::class, function (SendAdminWebPush $job) use ($admin) {
            return $job->adminId === $admin->id
                && $job->payload['topic'] === 'new_order'
                && $job->payload['title'] === 'New order placed'
                && $job->payload['body'] === 'ORD-1 · €120.00'
                && $job->payload['badge'] === 1
                && str_contains($job->payload['readUrl'], 'signature=');
        });
    }

    #[Test]
    public function an_admin_without_a_registered_device_gets_nothing_queued(): void
    {
        Bus::fake([SendAdminWebPush::class]);
        $this->admin(withDevice: false);

        $this->sendOrderBell();

        Bus::assertNotDispatched(SendAdminWebPush::class);
    }

    #[Test]
    public function notifications_the_bell_cannot_show_are_not_pushed(): void
    {
        Bus::fake([SendAdminWebPush::class]);
        $admin = $this->admin();

        // No data.format = 'filament' → invisible in the bell → must not buzz a phone either.
        $admin->notify(new class extends \Illuminate\Notifications\Notification
        {
            public function via($notifiable): array
            {
                return ['database'];
            }

            public function toArray($notifiable): array
            {
                return ['type' => 'refund_requested'];
            }
        });

        Bus::assertNotDispatched(SendAdminWebPush::class);
    }

    #[Test]
    public function the_dashboard_notification_class_is_pushed_with_its_topic(): void
    {
        Bus::fake([SendAdminWebPush::class]);
        $this->admin();

        app(AdminNotificationService::class)->createForAll(
            AdminNotificationCategory::System,
            'Queue job failed: X',
            'boom',
            '/admin/system/failed-jobs',
            ['push_topic' => 'job_failed'],
        );

        Bus::assertDispatched(SendAdminWebPush::class, fn ($job) => $job->payload['topic'] === 'job_failed'
            && str_ends_with($job->payload['url'], '/admin/system/failed-jobs'));
    }

    #[Test]
    public function contact_and_part_inquiry_notifications_now_reach_the_bell_and_push(): void
    {
        Bus::fake([SendAdminWebPush::class]);
        $admin = $this->admin();

        $admin->notify(new ContactMessageNotification('Jon', 'jon@example.test', 'Hello', 'Body text'));

        $row = $admin->notifications()->first();
        $this->assertSame('filament', $row->data['format']);
        $this->assertSame('Jon', $row->data['name']); // original keys survive

        Bus::assertDispatched(SendAdminWebPush::class, fn ($job) => $job->payload['topic'] === 'contact_message');
    }

    // ── Control layers ──────────────────────────────────────────────────

    #[Test]
    public function the_site_wide_switch_stops_everything(): void
    {
        Bus::fake([SendAdminWebPush::class]);
        $this->admin();
        app(SettingsService::class)->set('push.enabled', 'false');

        $this->sendOrderBell();

        Bus::assertNotDispatched(SendAdminWebPush::class);
    }

    #[Test]
    public function a_disabled_topic_is_not_pushed(): void
    {
        Bus::fake([SendAdminWebPush::class]);
        $this->admin();
        app(PushTopicRegistry::class)->find('new_order')->update(['push_enabled' => false]);

        $this->sendOrderBell();

        Bus::assertNotDispatched(SendAdminWebPush::class);
    }

    #[Test]
    public function topic_role_restrictions_are_enforced(): void
    {
        Bus::fake([SendAdminWebPush::class]);
        $owner = $this->admin('super_admin');
        $manager = $this->admin('manager');
        app(PushTopicRegistry::class)->find('new_order')->update(['allowed_roles' => ['super_admin']]);

        $this->sendOrderBell();

        Bus::assertDispatched(SendAdminWebPush::class, fn ($job) => $job->adminId === $owner->id);
        Bus::assertNotDispatched(SendAdminWebPush::class, fn ($job) => $job->adminId === $manager->id);
    }

    #[Test]
    public function an_admin_can_opt_out_of_a_topic_or_of_everything(): void
    {
        Bus::fake([SendAdminWebPush::class]);
        $quiet = $this->admin();
        $off = $this->admin('admin');
        AdminPushPreference::forAdmin($quiet)->update(['topic_overrides' => ['new_order' => false]]);
        AdminPushPreference::forAdmin($off)->update(['push_enabled' => false]);

        $this->sendOrderBell();

        Bus::assertNotDispatched(SendAdminWebPush::class);
    }

    #[Test]
    public function hide_details_replaces_the_body_but_keeps_the_title(): void
    {
        Bus::fake([SendAdminWebPush::class]);
        $admin = $this->admin();
        AdminPushPreference::forAdmin($admin)->update(['hide_details' => true]);

        $this->sendOrderBell();

        Bus::assertDispatched(SendAdminWebPush::class, fn ($job) => $job->payload['title'] === 'New order placed'
            && $job->payload['body'] === __('push.hidden_body'));
    }

    #[Test]
    public function the_site_default_for_hiding_details_applies_unless_the_admin_overrides_it(): void
    {
        Bus::fake([SendAdminWebPush::class]);
        $follows = $this->admin();
        $overrides = $this->admin('admin');
        AdminPushPreference::forAdmin($overrides)->update(['hide_details' => false]);
        app(SettingsService::class)->set('push.hide_details', 'true');

        $this->sendOrderBell();

        Bus::assertDispatched(SendAdminWebPush::class, fn ($job) => $job->adminId === $follows->id && $job->payload['body'] === __('push.hidden_body'));
        Bus::assertDispatched(SendAdminWebPush::class, fn ($job) => $job->adminId === $overrides->id && $job->payload['body'] === 'ORD-1 · €120.00');
    }

    #[Test]
    public function an_unknown_topic_is_registered_automatically_and_pushed(): void
    {
        Bus::fake([SendAdminWebPush::class]);
        $this->admin();

        AdminNotifier::toRoles(['*'], Notification::make()->title('Brand new thing')->viewData(['push_topic' => 'Warehouse Low Stock!']));

        $topic = PushTopic::where('key', 'warehouse_low_stock')->first();
        $this->assertNotNull($topic);
        $this->assertTrue($topic->is_auto);
        Bus::assertDispatched(SendAdminWebPush::class, fn ($job) => $job->payload['topic'] === 'warehouse_low_stock');
    }

    // ── Quiet hours ─────────────────────────────────────────────────────

    #[Test]
    public function quiet_hours_hold_normal_alerts_but_let_urgent_ones_through(): void
    {
        Bus::fake([SendAdminWebPush::class]);
        $admin = $this->admin();
        AdminPushPreference::forAdmin($admin)->update(['quiet_enabled' => true, 'quiet_start' => '00:00', 'quiet_end' => '23:59']);

        $this->sendOrderBell(); // normal
        AdminNotifier::toRoles(['*'], Notification::make()->title('Refund requested')->viewData(['push_topic' => 'refund_requested'])); // urgent

        $this->assertSame(1, AdminPushDeferred::where('admin_id', $admin->id)->count());
        Bus::assertDispatchedTimes(SendAdminWebPush::class, 1);
        Bus::assertDispatched(SendAdminWebPush::class, fn ($job) => $job->payload['topic'] === 'refund_requested' && $job->payload['urgent'] === true);
    }

    #[Test]
    public function quiet_hours_window_can_wrap_past_midnight(): void
    {
        $service = app(AdminPushService::class);
        $pref = new AdminPushPreference(['quiet_enabled' => true, 'quiet_start' => '22:00', 'quiet_end' => '08:00']);

        app(SettingsService::class)->set('general.timezone', 'UTC');
        $at = fn (string $time) => Carbon::parse("2026-10-06 {$time}", 'UTC');

        $this->assertTrue($service->inQuietHours($pref, $at('23:30')));
        $this->assertTrue($service->inQuietHours($pref, $at('03:00')));
        $this->assertFalse($service->inQuietHours($pref, $at('08:00')));
        $this->assertFalse($service->inQuietHours($pref, $at('12:00')));
        $this->assertFalse($service->inQuietHours(new AdminPushPreference(['quiet_enabled' => false]), $at('23:30')));
    }

    #[Test]
    public function deferred_alerts_are_delivered_as_one_digest_when_quiet_hours_end(): void
    {
        Bus::fake([SendAdminWebPush::class]);
        $admin = $this->admin();
        $pref = AdminPushPreference::forAdmin($admin);

        foreach (['One', 'Two', 'Three', 'Four'] as $title) {
            AdminPushDeferred::create(['admin_id' => $admin->id, 'topic' => 'new_order', 'title' => $title, 'body' => 'x']);
        }

        // Still inside quiet hours: nothing is sent, nothing is lost.
        $pref->update(['quiet_enabled' => true, 'quiet_start' => '00:00', 'quiet_end' => '23:59']);
        $this->artisan('oeparts:admin-push:flush')->assertSuccessful();
        Bus::assertNotDispatched(SendAdminWebPush::class);
        $this->assertSame(4, AdminPushDeferred::count());

        // Quiet hours over: a single summary goes out and the queue empties.
        $pref->update(['quiet_enabled' => false]);
        $this->artisan('oeparts:admin-push:flush')->assertSuccessful();

        Bus::assertDispatchedTimes(SendAdminWebPush::class, 1);
        Bus::assertDispatched(SendAdminWebPush::class, fn ($job) => str_contains($job->payload['title'], '4')
            && str_contains($job->payload['body'], 'One')
            && str_contains($job->payload['body'], __('push.digest_more', ['count' => 1])));
        $this->assertSame(0, AdminPushDeferred::count());
    }

    #[Test]
    public function the_flush_command_prunes_stale_rows(): void
    {
        $admin = $this->admin();
        AdminPushPreference::forAdmin($admin)->update(['quiet_enabled' => true, 'quiet_start' => '00:00', 'quiet_end' => '23:59']);
        $old = AdminPushDeferred::create(['admin_id' => $admin->id, 'topic' => 'new_order', 'title' => 'Old']);
        $old->forceFill(['created_at' => now()->subDays(3)])->save();
        AdminPushDelivery::create(['admin_id' => $admin->id, 'status' => 'sent'])->forceFill(['created_at' => now()->subDays(40)])->save();

        $this->artisan('oeparts:admin-push:flush')->assertSuccessful();

        $this->assertSame(0, AdminPushDeferred::count());
        $this->assertSame(0, AdminPushDelivery::count());
    }

    // ── Delivery job ────────────────────────────────────────────────────

    private function fakeClient(array $result): void
    {
        $this->app->instance(WebPushClient::class, new class($result) extends WebPushClient
        {
            public function __construct(private array $result) {}

            public function send(Collection $subscriptions, array $payload, bool $urgent): array
            {
                return $subscriptions->mapWithKeys(fn ($s) => [$s->id => $this->result])->all();
            }
        });
    }

    #[Test]
    public function a_successful_delivery_is_recorded(): void
    {
        $admin = $this->admin();
        $this->fakeClient(['ok' => true, 'expired' => false, 'status' => 201, 'error' => null]);

        SendAdminWebPush::dispatchSync($admin->id, ['topic' => 'new_order', 'title' => 'T']);

        $sub = $admin->pushSubscriptions()->first();
        $this->assertNotNull($sub->last_success_at);
        $this->assertDatabaseHas('admin_push_deliveries', ['admin_id' => $admin->id, 'status' => 'sent', 'http_status' => 201]);
    }

    #[Test]
    public function an_expired_subscription_is_deleted(): void
    {
        $admin = $this->admin();
        $this->fakeClient(['ok' => false, 'expired' => true, 'status' => 410, 'error' => 'Gone']);

        SendAdminWebPush::dispatchSync($admin->id, ['topic' => 'new_order', 'title' => 'T']);

        $this->assertSame(0, $admin->pushSubscriptions()->count());
        $this->assertDatabaseHas('admin_push_deliveries', ['status' => 'expired']);
    }

    #[Test]
    public function a_device_that_keeps_failing_is_dropped_after_five_strikes(): void
    {
        $admin = $this->admin();
        $this->fakeClient(['ok' => false, 'expired' => false, 'status' => 500, 'error' => 'server error']);

        for ($i = 1; $i <= 4; $i++) {
            SendAdminWebPush::dispatchSync($admin->id, ['topic' => 'new_order', 'title' => 'T']);
        }
        $this->assertSame(4, $admin->pushSubscriptions()->first()->failure_count);

        SendAdminWebPush::dispatchSync($admin->id, ['topic' => 'new_order', 'title' => 'T']);
        $this->assertSame(0, $admin->pushSubscriptions()->count());
    }

    #[Test]
    public function the_push_job_failing_never_raises_a_job_failed_alert(): void
    {
        $this->assertContains('SendAdminWebPush', (new \ReflectionClassConstant(NotifyAdminsOnJobFailure::class, 'EXCLUDED_JOB_NAMES'))->getValue());
    }

    // ── Mark as read / poll / test ──────────────────────────────────────

    #[Test]
    public function the_signed_mark_read_link_marks_exactly_that_notification_read(): void
    {
        Bus::fake([SendAdminWebPush::class]);
        $admin = $this->admin();
        $other = $this->admin('admin');
        $this->sendOrderBell();

        $note = $admin->notifications()->first();
        $url = URL::temporarySignedRoute('admin.push.read', now()->addHour(), ['admin' => $admin->id, 'id' => $note->id]);

        $this->postJson($url)->assertOk()->assertJsonPath('unread', 0);
        $this->assertNotNull($note->fresh()->read_at);
        $this->assertNull($other->notifications()->first()->read_at);
    }

    #[Test]
    public function the_mark_read_link_rejects_a_forged_or_tampered_url(): void
    {
        $admin = $this->admin();
        $this->sendOrderBell();
        $note = $admin->notifications()->first();

        $this->postJson(route('admin.push.read', ['admin' => $admin->id, 'id' => $note->id]))->assertStatus(403);

        $url = URL::temporarySignedRoute('admin.push.read', now()->addHour(), ['admin' => $admin->id, 'id' => $note->id]);
        $this->postJson(str_replace('/admin/push/read/'.$admin->id, '/admin/push/read/'.($admin->id + 1), $url))->assertStatus(403);
        $this->assertNull($note->fresh()->read_at);
    }

    #[Test]
    public function the_mark_read_link_cannot_touch_another_admins_notification(): void
    {
        $victim = $this->admin();
        $attacker = $this->admin('admin');
        $this->sendOrderBell();
        $note = $victim->notifications()->first();

        $url = URL::temporarySignedRoute('admin.push.read', now()->addHour(), ['admin' => $attacker->id, 'id' => $note->id]);
        $this->postJson($url)->assertOk();

        $this->assertNull($note->fresh()->read_at);
    }

    #[Test]
    public function poll_reports_new_notifications_since_the_cursor_and_the_unread_count(): void
    {
        $admin = $this->admin();
        $first = $this->actingAs($admin, 'admin')->getJson(route('admin.push.poll'))->assertOk();
        $cursor = $first->json('cursor');
        $this->assertSame([], $first->json('items'));

        $this->travel(5)->seconds();
        $this->sendOrderBell();

        $second = $this->actingAs($admin, 'admin')->getJson(route('admin.push.poll', ['cursor' => $cursor]))->assertOk();
        $this->assertSame(1, $second->json('unread'));
        $this->assertSame('New order placed', $second->json('items.0.title'));
        $this->assertTrue($second->json('items.0.sound'));
    }

    #[Test]
    public function poll_respects_the_admins_topic_opt_out(): void
    {
        $admin = $this->admin();
        AdminPushPreference::forAdmin($admin)->update(['topic_overrides' => ['new_order' => false]]);
        $cursor = now()->subMinute()->toIso8601String();
        $this->sendOrderBell();

        $this->actingAs($admin, 'admin')->getJson(route('admin.push.poll', ['cursor' => $cursor]))
            ->assertOk()
            ->assertJsonPath('items', []);
    }

    #[Test]
    public function the_test_endpoint_reports_success_and_failure_honestly(): void
    {
        $admin = $this->admin();

        $this->fakeClient(['ok' => true, 'expired' => false, 'status' => 201, 'error' => null]);
        $this->actingAs($admin, 'admin')->postJson(route('admin.push.test'))->assertOk()->assertJsonPath('ok', true);

        $this->fakeClient(['ok' => false, 'expired' => false, 'status' => 400, 'error' => 'bad vapid']);
        $this->actingAs($admin, 'admin')->postJson(route('admin.push.test'))
            ->assertStatus(502)
            ->assertJsonPath('ok', false);

        $loner = $this->admin('admin', withDevice: false);
        $this->actingAs($loner, 'admin')->postJson(route('admin.push.test'))->assertStatus(422);
    }

    #[Test]
    public function an_admin_can_only_test_their_own_devices(): void
    {
        $mine = $this->admin();
        $theirs = $this->admin('admin');
        $this->fakeClient(['ok' => true, 'expired' => false, 'status' => 201, 'error' => null]);

        $this->actingAs($mine, 'admin')
            ->postJson(route('admin.push.test'), ['subscription_id' => $theirs->pushSubscriptions()->first()->id])
            ->assertStatus(422);
    }

    // ── VAPID ───────────────────────────────────────────────────────────

    #[Test]
    public function vapid_keys_are_generated_once_and_stay_stable(): void
    {
        $keys = app(VapidKeys::class);

        $first = $keys->publicKey();
        $this->assertSame(87, strlen($first));
        $this->assertSame($first, $keys->publicKey());
        $this->assertSame($first, app(VapidKeys::class)->publicKey());
        $this->assertNotEmpty($keys->forWebPush()['privateKey']);
    }

    // ── Filament pages ──────────────────────────────────────────────────

    #[Test]
    public function the_alert_preferences_page_saves_personal_choices(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin');

        Livewire::test(AlertPreferences::class)
            ->assertSee('Chrome · Windows')
            ->set('soundEnabled', false)
            ->set('hideDetails', 'hide')
            ->set('quietEnabled', true)
            ->set('quietStart', '21:30')
            ->set('quietEnd', '07:15')
            ->set('topicOn.new_order', false)
            ->call('save')
            ->assertHasNoErrors();

        $pref = AdminPushPreference::forAdmin($admin);
        $this->assertFalse($pref->sound_enabled);
        $this->assertTrue($pref->hide_details);
        $this->assertTrue($pref->quiet_enabled);
        $this->assertSame('21:30', $pref->quiet_start);
        $this->assertFalse($pref->topicOverride('new_order'));
        $this->assertNull($pref->topicOverride('contact_message'));
    }

    #[Test]
    public function the_alert_preferences_page_rejects_a_bad_time(): void
    {
        $this->actingAs($this->admin(), 'admin');

        Livewire::test(AlertPreferences::class)
            ->set('quietStart', '25:99')
            ->call('save')
            ->assertHasErrors(['quietStart']);
    }

    #[Test]
    public function the_alert_preferences_page_removes_only_my_own_device(): void
    {
        $admin = $this->admin();
        $other = $this->admin('admin');
        $this->actingAs($admin, 'admin');

        Livewire::test(AlertPreferences::class)
            ->call('removeDevice', $other->pushSubscriptions()->first()->id)
            ->call('removeDevice', $admin->pushSubscriptions()->first()->id);

        $this->assertSame(1, AdminPushSubscription::count());
        $this->assertSame($other->id, AdminPushSubscription::first()->admin_id);
    }

    #[Test]
    public function the_alert_preferences_page_hides_topics_the_role_cannot_receive(): void
    {
        $manager = $this->admin('manager');
        app(PushTopicRegistry::class)->find('payment_dispute')->update(['allowed_roles' => ['super_admin']]);
        $this->actingAs($manager, 'admin');

        $topics = Livewire::test(AlertPreferences::class)->instance()->availableTopics()->pluck('key');

        $this->assertNotContains('payment_dispute', $topics);
        $this->assertContains('new_order', $topics);
    }

    #[Test]
    public function the_control_center_saves_topic_settings_and_the_master_switch(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $component = Livewire::test(AlertControlCenter::class);
        $index = collect($component->get('topics'))->search(fn ($t) => $t['key'] === 'new_order');

        $component
            ->set('enabled', false)
            ->set('hideDetails', true)
            ->set("topics.{$index}.urgency", 'urgent')
            ->set("topics.{$index}.sound_enabled", false)
            ->set("topics.{$index}.allowed_roles", ['super_admin', 'admin'])
            ->call('save')
            ->assertHasNoErrors();

        $topic = PushTopic::where('key', 'new_order')->first();
        $this->assertSame('urgent', $topic->urgency);
        $this->assertFalse($topic->sound_enabled);
        $this->assertSame(['super_admin', 'admin'], $topic->allowed_roles);
        $this->assertFalse(app(AdminPushService::class)->isGloballyEnabled());
        $this->assertTrue(app(AdminPushService::class)->hideDetailsByDefault());
    }

    #[Test]
    public function only_site_admins_can_open_the_control_center_but_any_admin_can_open_my_alerts(): void
    {
        $this->actingAs($this->admin('manager', withDevice: false), 'admin');
        $this->assertFalse(AlertControlCenter::canAccess());
        $this->assertTrue(AlertPreferences::canAccess());

        $this->actingAs($this->admin('admin', withDevice: false), 'admin');
        $this->assertTrue(AlertControlCenter::canAccess());
    }

    #[Test]
    public function the_scheduler_runs_the_flush_command_every_minute(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command ?? '', 'oeparts:admin-push:flush'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue(class_exists(AdminPushFlushDeferred::class));
    }
}
