<?php

namespace Tests\Feature;

use App\Filament\Pages\System\CleanupDashboard;
use App\Models\ActivityLog;
use App\Models\Admin;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Admin Cleanup dashboard — built after a real production incident (a
 * git-managed install's update was killed mid-git_checkout, leaving dev-only
 * files on a live site with no way for an operator to see or fix that short of
 * SSH). Two different risk profiles by design: dev-file removal is a real
 * delete (an exact, already-tested path list), the database audit section is
 * report-only and must never have any delete capability at all.
 */
class CleanupDashboardTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, RolesSeeder::class]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Same footgun as InterruptedUpdateResumerTest / GitUpdaterTest: base_path()
        // in this test run IS the real project checkout, which has a real .git dir —
        // isolate root_path so these tests don't touch it.
        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-cleanuppage-'.getmypid();
        @mkdir($this->root, 0775, true);
        config(['updates.root_path' => $this->root]);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
        parent::tearDown();
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$entry;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private function adminWithRole(string $role): Admin
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole($role);

        return $admin;
    }

    #[Test]
    public function a_super_admin_can_open_it(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        $this->get(CleanupDashboard::getUrl())->assertOk();
    }

    #[Test]
    public function a_role_without_manage_cleanup_is_forbidden(): void
    {
        $this->actingAs($this->adminWithRole('support'), 'admin');

        $this->get(CleanupDashboard::getUrl())->assertForbidden();
    }

    #[Test]
    public function on_a_non_git_install_it_says_so_and_never_scans(): void
    {
        // No .git dir under $this->root.
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        $component = Livewire::test(CleanupDashboard::class);

        $component->assertSet('devFilesPreview', null);
        $component->assertSeeText('not git-managed');
    }

    #[Test]
    public function on_a_git_install_it_scans_automatically_on_load_and_reports_clean(): void
    {
        @mkdir($this->root.'/.git', 0775, true);
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        $component = Livewire::test(CleanupDashboard::class);

        $component->assertSet('devFilesPreview', []);
        $component->assertSeeText('Clean — nothing to remove.');
    }

    #[Test]
    public function it_finds_and_removes_dev_only_files_leaving_env_and_storage_alone(): void
    {
        @mkdir($this->root.'/.git', 0775, true);
        @mkdir($this->root.'/tests', 0775, true);
        file_put_contents($this->root.'/tests/Foo.php', 'x');
        file_put_contents($this->root.'/phpstan.neon', 'x');
        file_put_contents($this->root.'/.env', 'APP_KEY=keep-me');
        @mkdir($this->root.'/storage', 0775, true);
        file_put_contents($this->root.'/storage/keep.txt', 'keep me');

        $admin = $this->adminWithRole('super_admin');
        $this->actingAs($admin, 'admin');

        $component = Livewire::test(CleanupDashboard::class);
        $this->assertCount(2, $component->get('devFilesPreview'));

        $component->call('removeDevFiles');

        $this->assertDirectoryDoesNotExist($this->root.'/tests');
        $this->assertFileDoesNotExist($this->root.'/phpstan.neon');
        $this->assertFileExists($this->root.'/.env');
        $this->assertSame('APP_KEY=keep-me', file_get_contents($this->root.'/.env'));
        $this->assertFileExists($this->root.'/storage/keep.txt');

        // Re-scanned after removal — the dashboard reflects reality immediately.
        $component->assertSet('devFilesPreview', []);

        $this->assertTrue(
            ActivityLog::where('admin_id', $admin->id)->where('action', 'cleanup_dev_files')->exists(),
            'the two most destructive admin actions in this app already write to Activity Log — this one should too.'
        );
    }

    #[Test]
    public function the_schema_audit_is_report_only_and_logs_the_run(): void
    {
        $admin = $this->adminWithRole('super_admin');
        $this->actingAs($admin, 'admin');

        DB::statement('CREATE TABLE a_manual_table (id integer)');

        $component = Livewire::test(CleanupDashboard::class);
        $component->call('runSchemaAudit');

        $audit = $component->get('schemaAudit');
        $this->assertContains('a_manual_table', $audit['unexpected']);
        $component->assertSeeText('a_manual_table');

        // Report-only: the table the audit just flagged must still be there — this
        // page has no action anywhere that could have dropped it.
        $this->assertContains('a_manual_table', Schema::getTableListing(schemaQualified: false));

        $this->assertTrue(ActivityLog::where('admin_id', $admin->id)->where('action', 'schema_audit_run')->exists());
    }

    #[Test]
    public function the_cleanup_page_never_exposes_any_action_that_drops_a_table(): void
    {
        // Belt-and-suspenders against this page ever growing a delete button for
        // the schema audit section — scan every public method for anything that
        // looks like it could drop/truncate a table.
        $methods = (new \ReflectionClass(CleanupDashboard::class))->getMethods(\ReflectionMethod::IS_PUBLIC);
        $names = array_map(fn ($m) => $m->getName(), $methods);

        foreach ($names as $name) {
            $this->assertStringNotContainsStringIgnoringCase('drop', $name);
            $this->assertStringNotContainsStringIgnoringCase('truncate', $name);
            $this->assertStringNotContainsStringIgnoringCase('deleteTable', $name);
        }
    }
}
