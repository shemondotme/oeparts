<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Setting;
use App\Notifications\SeoAuditAlert;
use App\Services\SeoAuditMonitor;
use App\Services\SettingsService;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The nightly audit tells the admins about a CRITICAL problem once when it
 * appears, again only as a weekly reminder, and never when switched off.
 * (The test environment is "testing", so "not in production mode" is the
 * standing critical problem used here.)
 */
class SeoAuditMonitorTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesSeeder::class, SettingsSeeder::class]);
        Cache::flush();
        Notification::fake();

        $this->superAdmin = Admin::factory()->create(['is_active' => true]);
        $this->superAdmin->assignRole('super_admin');
    }

    private function monitor(): SeoAuditMonitor
    {
        return app(SeoAuditMonitor::class);
    }

    #[Test]
    public function a_new_critical_problem_is_announced_to_the_active_admins(): void
    {
        $inactive = Admin::factory()->create(['is_active' => false]);
        $inactive->assignRole('super_admin');
        $other = Admin::factory()->create(['is_active' => true]); // no admin role

        $result = $this->monitor()->run();

        $this->assertContains('env', $result['announced']);
        $this->assertSame(1, $result['notified']);
        Notification::assertSentTo($this->superAdmin, SeoAuditAlert::class, fn (SeoAuditAlert $n) => collect($n->issues)->contains(fn ($i) => str_contains($i['title'], 'production mode') && $i['still'] === false));
        Notification::assertNotSentTo($inactive, SeoAuditAlert::class);
        Notification::assertNotSentTo($other, SeoAuditAlert::class);
    }

    #[Test]
    public function the_same_problem_is_not_announced_again_on_the_next_run(): void
    {
        $this->monitor()->run();
        Notification::fake();

        $second = $this->monitor()->run();

        $this->assertSame([], $second['announced']);
        $this->assertSame(0, $second['notified']);
        Notification::assertNothingSent();
    }

    #[Test]
    public function a_problem_still_open_after_a_week_is_repeated_as_a_reminder(): void
    {
        $this->monitor()->run();
        Notification::fake();

        $this->travel(8)->days();
        $result = $this->monitor()->run();

        $this->assertContains('env', $result['announced']);
        Notification::assertSentTo($this->superAdmin, SeoAuditAlert::class, fn (SeoAuditAlert $n) => collect($n->issues)->every(fn ($i) => $i['still'] === true));
    }

    #[Test]
    public function a_fixed_problem_is_reported_resolved_and_announced_afresh_if_it_returns(): void
    {
        $this->monitor()->run();

        $this->app['env'] = 'production';
        config(['app.url' => 'https://shop.example.org']);
        $fixed = $this->monitor()->run();
        $this->assertContains('env', $fixed['resolved']);

        Notification::fake();
        $this->app['env'] = 'testing';
        $again = $this->monitor()->run();
        $this->assertContains('env', $again['announced']);
        Notification::assertSentTo($this->superAdmin, SeoAuditAlert::class);
    }

    #[Test]
    public function switching_the_alerts_off_sends_nothing_and_does_not_swallow_the_alert_for_later(): void
    {
        Setting::updateOrCreate(['group' => 'seo', 'key' => 'audit_alerts_enabled'], ['value' => 'false', 'type' => 'boolean', 'is_encrypted' => false]);
        app(SettingsService::class)->forget('seo');

        $this->monitor()->run();
        Notification::assertNothingSent();

        Setting::where(['group' => 'seo', 'key' => 'audit_alerts_enabled'])->update(['value' => 'true']);
        app(SettingsService::class)->forget('seo');

        $this->monitor()->run();
        Notification::assertSentTo($this->superAdmin, SeoAuditAlert::class);
    }

    #[Test]
    public function a_report_only_run_alerts_nobody_and_does_not_use_up_the_announcement(): void
    {
        $quiet = $this->monitor()->run(notify: false);
        Notification::assertNothingSent();
        $this->assertSame(0, $quiet['notified']);

        $this->monitor()->run();
        Notification::assertSentTo($this->superAdmin, SeoAuditAlert::class);
    }

    #[Test]
    public function the_command_prints_a_summary_and_never_fails(): void
    {
        $exit = Artisan::call('seo:audit');
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Critical problems:', $output);
        $this->assertStringContainsString('Announced:', $output);
    }

    #[Test]
    public function the_alert_renders_an_email_and_a_bell_entry(): void
    {
        $alert = new SeoAuditAlert([['title' => 'The site is not in production mode', 'detail' => 'robots.txt blocks everything.', 'still' => false]], 'OeParts');

        $mail = $alert->toMail($this->superAdmin);
        $this->assertStringContainsString('SEO alert: 1 critical issue found', $mail->subject);

        $database = $alert->toDatabase($this->superAdmin);
        $this->assertSame('seo_audit_alert', $database['type']);
        $this->assertSame('SEO alert: 1 critical issue found', $database['title']);
    }

    #[Test]
    public function the_scheduled_run_skips_the_catalog_wide_photo_count(): void
    {
        $ids = array_column(app(\App\Services\SeoAuditService::class)->run(skipHeavy: true)['checks'], 'id');

        $this->assertNotContains('product_images', $ids);
        $this->assertContains('product_images', array_column(app(\App\Services\SeoAuditService::class)->run()['checks'], 'id'));
    }
}
