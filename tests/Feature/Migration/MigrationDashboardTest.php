<?php

namespace Tests\Feature\Migration;

use App\Filament\Pages\System\MigrationDashboard;
use App\Filament\Widgets\Migration\MigrationHistoryTable;
use App\Filament\Widgets\Migration\MigrationPackagesTable;
use App\Models\Admin;
use App\Models\PackageShareLink;
use App\Security\PackageAudit;
use App\Services\Migration\Export\PackageOutbox;
use App\Services\Migration\Transport\ShareLinkService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** System → Migration, first part: who may open it, the Packages tab and its password-gated actions, History and Limits. */
class MigrationDashboardTest extends TestCase
{
    use RefreshDatabase;

    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oeparts-dash-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/exports', 0700, true);
        $this->app->instance(PackageOutbox::class, new PackageOutbox($this->dir.'/exports'));
        $this->app->instance(PackageAudit::class, new PackageAudit($this->dir.'/history.ndjson'));

        $body = 'OEPARTS1'.random_bytes(5000);
        file_put_contents($this->dir.'/exports/site.oeparts', $body);
        file_put_contents($this->dir.'/exports/site.oeparts.json', json_encode([
            'sha256' => hash('sha256', $body), 'size' => strlen($body), 'preset' => 'full_live', 'groups' => ['core', 'catalog'],
            'secrets_included' => true, 'consistency' => 'eventual', 'expires_at' => now()->addDays(7)->toIso8601String(),
        ]));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/{,*/}{,.}*', GLOB_BRACE) ?: [] as $file) {
            is_file($file) ? @unlink($file) : null;
        }

        @rmdir($this->dir.'/exports');
        @rmdir($this->dir);
        RateLimiter::clear('reauth:'.'1|127.0.0.1');

        parent::tearDown();

        gc_collect_cycles(); // a full admin page is one big cycle of objects; free it before the next test starts
    }

    private function admin(string $role = 'super_admin'): Admin
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole($role);
        RateLimiter::clear('reauth:'.$admin->getKey().'|127.0.0.1');

        return $admin;
    }

    // ---- the page --------------------------------------------------------------------------------------------------

    #[Test]
    public function a_super_admin_can_open_the_page(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->get(MigrationDashboard::getUrl())->assertOk()->assertSee('Migration');
    }

    #[Test]
    public function a_support_role_cannot_open_the_page(): void
    {
        $this->actingAs($this->admin('support'), 'admin');

        $this->get(MigrationDashboard::getUrl())->assertForbidden();
    }

    #[Test]
    public function the_page_shows_its_tabs_and_the_limits(): void
    {
        $this->actingAs($this->admin(), 'admin');

        Livewire::test(MigrationDashboard::class)
            ->assertSuccessful()
            ->assertSee('Packages')
            ->assertSee('Share links')
            ->assertSee('History')
            ->assertSee('Limits')
            ->assertSee('Largest package accepted')
            ->assertSee('Downloads at the same time');
    }

    #[Test]
    public function the_limits_tab_shows_only_numbers_that_something_applies(): void
    {
        $this->actingAs($this->admin(), 'admin');
        config(['migration.import.recovery_max_rows' => 123_456, 'migration.import.fresh_max_rows' => 777_001]);

        // the size of a site this page can replace is the recovery point's cap; a second "fresh" cap is not applied by anything
        Livewire::test(MigrationDashboard::class)
            ->assertSee('Rows a recovery point may hold')
            ->assertSee('123,456')
            ->assertDontSee('777,001')
            ->assertDontSee('count as a new, unused site');
    }

    #[Test]
    public function the_page_is_in_the_system_navigation_group(): void
    {
        $this->assertSame('System', MigrationDashboard::getNavigationGroup());
        $this->assertSame('system/migration', (new \ReflectionClass(MigrationDashboard::class))->getStaticPropertyValue('slug'));
    }

    // ---- packages ---------------------------------------------------------------------------------------------------

    #[Test]
    public function the_packages_tab_lists_what_this_site_exported_with_the_facts_that_matter(): void
    {
        $this->actingAs($this->admin(), 'admin');

        Livewire::test(MigrationPackagesTable::class)
            ->assertSee('site.oeparts')
            ->assertSee('Full live copy')
            ->assertSee('Includes secrets');
    }

    #[Test]
    public function download_needs_the_password_and_then_goes_to_a_signed_address(): void
    {
        $this->actingAs($this->admin(), 'admin');

        Livewire::test(MigrationPackagesTable::class)
            ->callTableAction('download', 'site.oeparts', ['password' => 'wrong'])
            ->assertHasTableActionErrors(['password']);

        Livewire::test(MigrationPackagesTable::class)
            ->callTableAction('download', 'site.oeparts', ['password' => 'password'])
            ->assertHasNoTableActionErrors()
            ->assertRedirectContains('/admin/migration/packages/site.oeparts');
    }

    #[Test]
    public function a_share_link_is_made_only_with_the_password_and_shown_in_a_notification(): void
    {
        $this->actingAs($this->admin(), 'admin');

        Livewire::test(MigrationPackagesTable::class)
            ->callTableAction('share', 'site.oeparts', ['hours' => 2, 'ip' => '', 'password' => 'nope'])
            ->assertHasTableActionErrors(['password']);
        $this->assertSame(0, PackageShareLink::query()->count());

        Livewire::test(MigrationPackagesTable::class)
            ->callTableAction('share', 'site.oeparts', ['hours' => 2, 'ip' => '203.0.113.9', 'password' => 'password'])
            ->assertHasNoTableActionErrors()
            ->assertNotified('One-time link made');

        $link = PackageShareLink::query()->firstOrFail();
        $this->assertSame('site.oeparts', $link->package_name);
        $this->assertSame('203.0.113.9', $link->bound_ip);
        $this->assertNotNull($link->created_by_admin_id);
    }

    #[Test]
    public function after_five_wrong_passwords_even_the_right_one_is_refused_for_a_while(): void
    {
        $this->actingAs($this->admin(), 'admin');

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(MigrationPackagesTable::class)->callTableAction('download', 'site.oeparts', ['password' => 'bad'.$i]);
        }

        Livewire::test(MigrationPackagesTable::class)
            ->callTableAction('download', 'site.oeparts', ['password' => 'password'])
            ->assertHasTableActionErrors(['password']);
    }

    #[Test]
    public function a_package_with_a_live_link_cannot_be_deleted_until_the_link_is_revoked(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $made = app(ShareLinkService::class)->create('site.oeparts');

        Livewire::test(MigrationPackagesTable::class)
            ->callTableAction('delete', 'site.oeparts', ['password' => 'password'])
            ->assertNotified('This package is in use');
        $this->assertFileExists($this->dir.'/exports/site.oeparts');

        app(ShareLinkService::class)->revoke((int) $made['link']->getKey());

        Livewire::test(MigrationPackagesTable::class)
            ->callTableAction('delete', 'site.oeparts', ['password' => 'password'])
            ->assertNotified('Package deleted');
        $this->assertFileDoesNotExist($this->dir.'/exports/site.oeparts');
        $this->assertStringContainsString('package.deleted', (string) file_get_contents($this->dir.'/history.ndjson'));
    }

    #[Test]
    public function without_the_export_permission_the_package_actions_do_not_exist_for_the_user(): void
    {
        $this->actingAs($this->admin('support'), 'admin');

        // the widget itself refuses to render for someone who cannot open the page
        $this->assertFalse(MigrationPackagesTable::canView());
        $this->assertFalse(MigrationHistoryTable::canView());
    }

    // ---- history ----------------------------------------------------------------------------------------------------

    #[Test]
    public function history_shows_the_events_newest_first_and_never_a_token(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $audit = app(PackageAudit::class);
        $audit->record('export.completed', 'run-1', ['package' => 'site.oeparts', 'size' => 5008]);
        $audit->record('share.created', 'share-1', ['package' => 'site.oeparts', 'token' => 'SHOULD-NEVER-APPEAR']);

        $component = Livewire::test(MigrationHistoryTable::class)
            ->assertSee('Export finished')
            ->assertSee('Link made')
            ->assertSee('site.oeparts')
            ->assertDontSee('SHOULD-NEVER-APPEAR');

        $html = $component->html();
        $this->assertLessThan(strpos($html, 'Export finished'), strpos($html, 'Link made'), 'newest first');
    }

    #[Test]
    public function history_with_nothing_in_it_says_so(): void
    {
        $this->actingAs($this->admin(), 'admin');

        Livewire::test(MigrationHistoryTable::class)->assertSee('Nothing has happened yet');
    }
}
