<?php

namespace Tests\Feature;

use App\Filament\Pages\System\SystemUpdates;
use App\Models\Admin;
use App\Models\UpdateHistory;
use App\Services\Updates\RecoveryWindowFlag;
use App\Services\Updates\UpdateChecker;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Update & Recovery System (Module 21) — Chunk 1.3 System Updates page.
 */
class SystemUpdatesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\RolesSeeder::class,
        ]);

        config()->set('updates.check.catalog_url', 'https://updates.test/releases.json');
        config()->set('updates.check.manifest_url', 'https://updates.test/version.json');
        Cache::forget(UpdateChecker::CACHE_KEY);
    }

    private function adminWithRole(string $role): Admin
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole($role);

        return $admin;
    }

    private function fakeUpdateAvailable(bool $security = false): void
    {
        Http::fake([
            'updates.test/*' => Http::response(['channel' => 'stable', 'releases' => [
                ['version' => '9.9.9', 'min_version_to_update_from' => '0.0.0', 'security' => $security,
                 'download_url' => 'https://x/oeparts.zip', 'changelog_url' => 'https://x/CHANGELOG.md'],
            ]], 200),
            '*' => Http::response('', 500),
        ]);
    }

    #[Test]
    public function super_admin_sees_the_updates_page_with_an_available_update(): void
    {
        $this->fakeUpdateAvailable(security: true);
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        $this->get(SystemUpdates::getUrl())
            ->assertSuccessful()
            ->assertSee('System Updates')
            ->assertSee('Security update available')
            ->assertSee('9.9.9');
    }

    #[Test]
    public function a_role_without_view_updates_permission_is_forbidden(): void
    {
        $this->fakeUpdateAvailable();
        $this->actingAs($this->adminWithRole('support'), 'admin');

        $this->get(SystemUpdates::getUrl())->assertForbidden();
    }

    #[Test]
    public function check_now_refreshes_the_status(): void
    {
        $this->fakeUpdateAvailable();
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $component = Livewire::test(SystemUpdates::class)->call('checkNow');

        $status = $component->get('status');
        $this->assertTrue($status['update_available']);
        $this->assertSame('9.9.9', $status['latest_version']);
    }

    #[Test]
    public function the_nav_badge_reflects_a_cached_security_update(): void
    {
        $this->fakeUpdateAvailable(security: true);
        app(UpdateChecker::class)->check(force: true); // populate the cache

        $this->assertSame('1', SystemUpdates::getNavigationBadge());
        $this->assertSame('danger', SystemUpdates::getNavigationBadgeColor());
    }

    #[Test]
    public function the_nav_badge_is_hidden_without_a_cached_status(): void
    {
        Cache::forget(UpdateChecker::CACHE_KEY);

        $this->assertNull(SystemUpdates::getNavigationBadge());
        $this->assertNull(SystemUpdates::getNavigationBadgeColor());
    }

    #[Test]
    public function the_readiness_strip_shows_the_four_expected_pills(): void
    {
        $this->fakeUpdateAvailable();
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $summary = Livewire::test(SystemUpdates::class)->get('preflightSummary');

        $keys = collect($summary)->pluck('key')->sort()->values()->all();
        $this->assertSame(['disk', 'extensions', 'lock', 'signature'], $keys);

        foreach ($summary as $check) {
            $this->assertContains($check['status'], ['pass', 'warn', 'fail']);
        }
    }

    #[Test]
    public function the_readiness_strip_still_renders_when_up_to_date(): void
    {
        Http::fake([
            'updates.test/*' => Http::response(['channel' => 'stable', 'releases' => [
                ['version' => '0.0.1'],
            ]], 200),
            '*' => Http::response('', 500),
        ]);
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $summary = Livewire::test(SystemUpdates::class)->get('preflightSummary');

        $this->assertNotEmpty($summary);
    }

    #[Test]
    public function check_now_refreshes_the_preflight_summary_too(): void
    {
        $this->fakeUpdateAvailable();
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $summary = Livewire::test(SystemUpdates::class)->call('checkNow')->get('preflightSummary');

        $this->assertNotEmpty($summary);
    }

    #[Test]
    public function recovery_armed_status_reflects_the_arm_flag(): void
    {
        $state = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-updates-page-'.getmypid();
        @mkdir($state, 0775, true);
        config(['updates.state_path' => $state]);

        $this->fakeUpdateAvailable();
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(SystemUpdates::class)->assertSee('Not armed');

        app(RecoveryWindowFlag::class)->arm(['history_id' => 1]);
        Livewire::test(SystemUpdates::class)->assertSee('Armed');

        app(RecoveryWindowFlag::class)->disarm();
        @array_map('unlink', glob($state.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($state);
    }

    #[Test]
    public function recent_updates_strip_shows_the_last_three_rows_and_links_to_full_history(): void
    {
        $this->fakeUpdateAvailable();
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        foreach (range(1, 4) as $i) {
            UpdateHistory::create([
                'from_version' => "1.0.{$i}",
                'to_version' => '1.0.'.($i + 1),
                'channel' => 'stable',
                'status' => UpdateHistory::STATUS_SUCCESS,
                'step' => 'verify',
                'started_at' => now(),
                'finished_at' => now(),
            ]);
        }

        $component = Livewire::test(SystemUpdates::class);

        $this->assertCount(3, $component->instance()->recentUpdates());
        $component->assertSeeHtml(\App\Filament\Pages\System\UpdateHistoryPage::getUrl());
    }
}
