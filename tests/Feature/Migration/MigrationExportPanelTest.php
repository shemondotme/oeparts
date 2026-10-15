<?php

namespace Tests\Feature\Migration;

use App\Filament\Pages\System\MigrationDashboard;
use App\Models\Admin;
use App\Models\BackupRun;
use App\Models\Product;
use App\Models\Setting;
use App\Security\PackageAudit;
use App\Security\PackagePathPolicy;
use App\Services\Backup\BackupLock;
use App\Services\Migration\Export\AllowlistWalker;
use App\Services\Migration\Export\ExportFreeze;
use App\Services\Migration\Export\ExportManager;
use App\Services\Migration\Export\FileCounter;
use App\Services\Migration\Export\FreezeState;
use App\Services\Migration\Export\MigrationQueueProbeJob;
use App\Services\Migration\Export\PackageOutbox;
use App\Services\Migration\Export\RunExportJob;
use App\Services\Migration\Export\Stages\ExportFilesStage;
use App\Services\Migration\KeyEscrow;
use App\Services\Migration\Package\Kdf;
use App\Services\Migration\Package\PackageFormat;
use App\Services\Migration\Package\PassphraseInput;
use App\Services\Migration\Ux\PassphraseGuidance;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\PlantsRows;
use Tests\TestCase;

/**
 * The Export tab: the wizard's choices and what it tells the person, what it refuses (weak passphrase, wrong password, too many
 * starts, a freeze nobody can drive), and a live-copy export started and carried to the end by the page's own poll.
 *
 * Everything the export touches (packages, keys, history, the files it counts and packs) lives in a temporary folder.
 */
class MigrationExportPanelTest extends TestCase
{
    use PlantsRows;
    use RefreshDatabase;

    private string $dir = '';

    private ?string $probeStamp = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        Storage::fake('exportstage');
        config(['backup.staging_disk' => 'exportstage', 'migration.export.batch_seconds' => 0, 'migration.export.page_rows' => 50, 'queue.default' => 'sync']);

        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oeparts-exportpanel-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/exports', 0700, true);
        mkdir($this->dir.'/public/media', 0775, true);
        $this->app->instance(PackageOutbox::class, new PackageOutbox($this->dir.'/exports'));
        $this->app->instance(PackageAudit::class, new PackageAudit($this->dir.'/history.ndjson'));
        $this->app->instance(KeyEscrow::class, new KeyEscrow($this->dir.'/escrow', (string) config('app.key')));

        $disks = ['public' => ['path' => $this->dir.'/public', 'prefix' => 'storage/app/public'], 'local' => ['path' => $this->dir.'/local', 'prefix' => 'storage/app/private']];
        $this->app->when(ExportFilesStage::class)->needs('$disks')->give(fn (): array => $disks);
        $this->app->instance(FileCounter::class, new FileCounter(new AllowlistWalker(new PackagePathPolicy, $disks)));

        // the worker proof is a stamp file in the real migration folder: keep what was there and put it back
        $stamp = MigrationQueueProbeJob::stampPath();
        $this->probeStamp = is_file($stamp) ? (string) file_get_contents($stamp) : null;

        $this->clean();
        RateLimiter::clear('migration:export-starts');
        Product::factory()->count(3)->create();
        Setting::create(['group' => 'maintenance', 'key' => 'enabled', 'value' => '0', 'type' => 'boolean', 'is_encrypted' => false]);
    }

    protected function tearDown(): void
    {
        BackupRun::query()->exports()->get()->each(fn ($r) => app(KeyEscrow::class)->delete('export-'.$r->id));
        $this->clean();

        if ($this->probeStamp !== null) {
            file_put_contents(MigrationQueueProbeJob::stampPath(), $this->probeStamp);
        }

        exec('rm -rf '.escapeshellarg($this->dir));

        parent::tearDown();

        // A full admin page (tabs, two wizards, tables) and everything Livewire keeps of it is one big cycle of objects: without this
        // the memory of one test is still held when the next one starts, and a whole file runs out of memory.
        gc_collect_cycles();
    }

    private function clean(): void
    {
        app(BackupLock::class)->release();

        foreach ([FreezeState::file()->path(), MigrationQueueProbeJob::stampPath()] as $path) {
            foreach ([$path, $path.'.prev', $path.'.lock'] as $file) {
                @unlink($file);
            }
        }
    }

    private function signIn(string $role = 'super_admin'): Admin
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole($role);
        RateLimiter::clear('reauth:'.$admin->getKey().'|127.0.0.1');
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    private function provenWorker(): void
    {
        MigrationQueueProbeJob::file()->write(['at' => now()->toIso8601String(), 'pid' => 1, 'queue' => 'migration', 'connection' => 'database']);
    }

    /** Fill the wizard the way a person would and press start. */
    private function start($page, string $preset = 'catalog_only', string $password = 'password', ?string $passphrase = null)
    {
        $passphrase ??= PassphraseInput::generate();

        return $page
            ->set('exportData.preset', $preset)
            ->set('exportData.passphrase', $passphrase)
            ->set('exportData.passphrase_confirmation', $passphrase)
            ->set('exportData.password', $password)
            ->call('startExport');
    }

    // ---- who sees it, and what it shows ---------------------------------------------------------------------------

    #[Test]
    public function the_export_tab_shows_the_wizard_with_the_presets(): void
    {
        $this->signIn();

        Livewire::test(MigrationDashboard::class)
            ->assertSuccessful()
            ->assertSee('What to export')
            ->assertSee('Full live copy')
            ->assertSee('Staging copy, personal data scrambled')
            ->assertSee('Let me choose the parts')
            ->assertWizardStepExists(5, 'exportForm');
    }

    #[Test]
    public function an_admin_who_may_only_import_does_not_get_the_export_tab(): void
    {
        $admin = $this->signIn();
        $admin->givePermissionTo('import migration');
        $admin->syncRoles([]);

        Livewire::test(MigrationDashboard::class)->assertDontSee('What to export');
    }

    #[Test]
    public function an_admin_who_may_only_import_cannot_see_the_exported_packages_list(): void
    {
        $admin = $this->signIn();
        $admin->givePermissionTo('import migration');
        $admin->syncRoles([]);
        $this->actingAs($admin->fresh(), 'admin');

        $this->assertFalse(\App\Filament\Widgets\Migration\MigrationPackagesTable::canView());
        $this->assertTrue(\App\Filament\Widgets\Migration\MigrationHistoryTable::canView());
    }

    #[Test]
    public function an_admin_who_may_only_export_gets_the_export_tab(): void
    {
        $admin = $this->signIn();
        $admin->givePermissionTo('export migration');
        $admin->syncRoles([]);

        Livewire::test(MigrationDashboard::class)->assertSee('What to export');
    }

    #[Test]
    public function an_admin_who_may_only_import_cannot_start_or_poll_or_dismiss_an_export(): void
    {
        $admin = $this->signIn();
        $admin->givePermissionTo('import migration');
        $admin->syncRoles([]);

        foreach (['startExport', 'pollExport', 'cancelExport', 'dismissExport', 'reopenShop'] as $method) {
            Livewire::test(MigrationDashboard::class)->call($method)->assertForbidden();
        }

        $this->assertSame(0, BackupRun::query()->exports()->count());
    }

    #[Test]
    public function choosing_a_preset_fills_in_its_parts_and_staging_switches_secrets_off(): void
    {
        $this->signIn();

        $page = Livewire::test(MigrationDashboard::class)
            ->set('exportData.include_secrets', true)
            ->set('exportData.preset', 'staging');

        $groups = $page->get('exportData.groups');
        $this->assertContains('catalog', $groups);
        $this->assertNotContains('logs', $groups);
        $this->assertFalse($page->get('exportData.include_secrets'));

        $page->set('exportData.preset', 'catalog_only');
        $this->assertSame(['core', 'catalog', 'catalog_files', 'media'], array_values(array_intersect(['core', 'catalog', 'catalog_files', 'media', 'sales'], $page->get('exportData.groups'))));
    }

    #[Test]
    public function a_part_that_cannot_exist_without_another_adds_it_and_the_page_says_so_in_plain_words(): void
    {
        $this->signIn();

        Livewire::test(MigrationDashboard::class)
            ->set('exportData.preset', 'custom')
            ->set('exportData.groups', ['core', 'catalog_files'])
            ->assertNotified('Parts added')
            ->assertSee('Products was added, because Product pictures needs it.');
    }

    #[Test]
    public function a_part_whose_references_are_optional_adds_nothing_and_says_which_links_are_emptied(): void
    {
        $this->signIn();
        $this->plantRow('orders', ['order_number' => 'A-1']);

        // orders keep working without their customers and products: those links are cut in the copy (the resolver tests prove it)
        // and the page says so, with the rows it concerns, instead of pulling the customers in
        Livewire::test(MigrationDashboard::class)
            ->set('exportData.preset', 'custom')
            ->set('exportData.groups', ['core', 'sales'])
            ->assertDontSee('was added')
            ->assertSee('Good to know before you start')
            ->assertSee('Orders and payments: up to 1 rows carry a link (carrier_id, shipping_method_id, user_id)');
    }

    #[Test]
    public function a_full_copy_has_nothing_to_cut_so_the_notice_stays_away(): void
    {
        $this->signIn();

        Livewire::test(MigrationDashboard::class)->assertDontSee('is emptied in the copy');
    }

    #[Test]
    public function the_wizard_shows_the_size_and_time_estimate_and_the_key_derivation_in_force(): void
    {
        $this->signIn();

        $page = Livewire::test(MigrationDashboard::class)->assertSee('time: roughly')->assertSee('These are estimates.');

        $page->assertSee(Kdf::preferredKdfId() === PackageFormat::KDF_ARGON2ID ? 'locks packages with Argon2id' : 'cheaper for an attacker to guess at');
    }

    #[Test]
    public function a_server_without_argon2_explains_the_stricter_passphrase_rule(): void
    {
        $this->signIn();
        $this->app->instance(PassphraseGuidance::class, new PassphraseGuidance(PackageFormat::KDF_PBKDF2_SHA512));

        Livewire::test(MigrationDashboard::class)
            ->assertSee('cheaper for an attacker to guess at')
            ->assertSee('at least 20 strong characters');
    }

    #[Test]
    public function the_passphrase_rule_follows_the_key_derivation(): void
    {
        $argon = new PassphraseGuidance(PackageFormat::KDF_ARGON2ID);
        $pbkdf2 = new PassphraseGuidance(PackageFormat::KDF_PBKDF2_SHA512);
        $generated = PassphraseInput::generate();

        $this->assertNotNull($argon->problemWith(''));
        $this->assertNotNull($argon->problemWith(str_repeat('a', 13)));
        $this->assertNull($argon->problemWith('a-b-c-d-e-f-g-h'), '14 characters are enough under Argon2id');
        $this->assertNull($argon->problemWith($generated));

        $this->assertNotNull($pbkdf2->problemWith('a-b-c-d-e-f-g-h'), '14 characters are not enough under PBKDF2');
        $this->assertNotNull($pbkdf2->problemWith(str_repeat('a', 19)));
        $this->assertNotNull($pbkdf2->problemWith('passwordpassword1234'), 'long but guessable');
        $this->assertNull($pbkdf2->problemWith('Xk9#mQ2$vL7!pR4&tZ8w'), '20 strong characters');
        $this->assertNull($pbkdf2->problemWith($generated), 'the generated passphrase is accepted under both');
    }

    #[Test]
    public function the_wizard_refuses_a_passphrase_that_is_too_short_for_this_server_and_accepts_the_generated_one(): void
    {
        $this->signIn();
        $this->app->instance(PassphraseGuidance::class, new PassphraseGuidance(PackageFormat::KDF_PBKDF2_SHA512));

        $page = Livewire::test(MigrationDashboard::class)
            ->set('exportData.preset', 'catalog_only')
            ->set('exportData.passphrase', 'sixteen-characters')
            ->set('exportData.passphrase_confirmation', 'sixteen-characters')
            ->set('exportData.password', 'wrong')
            ->call('startExport')
            ->assertHasErrors(['exportData.passphrase']);

        $generated = PassphraseInput::generate();
        $page->set('exportData.passphrase', $generated)->set('exportData.passphrase_confirmation', $generated)
            ->call('startExport')
            ->assertHasNoErrors(['exportData.passphrase'])
            ->assertHasErrors(['exportData.password']); // the passphrase passed; the wrong password stopped it

        $this->assertSame(0, BackupRun::query()->exports()->count());
    }

    #[Test]
    public function a_staging_copy_lists_what_is_not_scrambled_and_a_full_copy_does_not(): void
    {
        $this->signIn();

        $page = Livewire::test(MigrationDashboard::class)->assertDontSee('What is NOT scrambled in this copy');

        $page->set('exportData.preset', 'staging')
            ->assertSee('What is NOT scrambled in this copy')
            ->assertSee('Administrator accounts keep their real names');
    }

    // ---- a freeze nobody can drive ---------------------------------------------------------------------------------

    #[Test]
    public function on_a_sync_queue_the_freeze_option_is_replaced_by_the_command_to_run(): void
    {
        $this->signIn();
        config(['queue.default' => 'sync']);

        Livewire::test(MigrationDashboard::class)
            ->set('exportData.preset', 'catalog_only')
            ->assertSee('QUEUE_CONNECTION=sync')
            ->assertSee('php artisan oeparts:migrate:export --preset=catalog_only --freeze --generate-passphrase');
    }

    #[Test]
    public function on_a_real_queue_without_a_proven_worker_the_page_shows_the_worker_line_and_the_command(): void
    {
        $this->signIn();
        config(['queue.default' => 'database']);

        Livewire::test(MigrationDashboard::class)
            ->assertSee('no queue worker has proven')
            ->assertSee('php artisan queue:work --queue=migration,critical,default,low')
            ->assertSee('php artisan oeparts:migrate:export --preset=full_live --freeze --generate-passphrase');
    }

    #[Test]
    public function a_proven_worker_makes_the_freeze_available_without_the_warning(): void
    {
        $this->signIn();
        config(['queue.default' => 'database']);
        $this->provenWorker();

        Livewire::test(MigrationDashboard::class)
            ->assertSee('Freeze, then copy')
            ->assertDontSee('Not available from this page');
    }

    #[Test]
    public function on_a_sync_queue_a_freeze_export_is_refused_with_the_reason_and_the_shop_stays_open(): void
    {
        $this->signIn();
        config(['queue.default' => 'sync']);

        $page = Livewire::test(MigrationDashboard::class)->set('exportData.consistency', 'freeze');
        $this->start($page)->assertHasNoErrors()->assertNotified('Cannot freeze from this page');

        $this->assertSame(0, BackupRun::query()->exports()->count());
        $this->assertNull(FreezeState::read());
        $this->assertFalse((bool) settings('maintenance.enabled'));
    }

    #[Test]
    public function on_a_real_queue_with_no_proven_worker_a_freeze_export_is_refused_and_the_shop_stays_open(): void
    {
        $this->signIn();
        config(['queue.default' => 'database']);

        $page = Livewire::test(MigrationDashboard::class)->set('exportData.consistency', 'freeze');
        $this->start($page)->assertHasNoErrors()->assertNotified('Nothing picked up the export');

        $this->assertSame(0, BackupRun::query()->exports()->count());
        $this->assertNull(FreezeState::read());
        $this->assertFalse((bool) settings('maintenance.enabled'));
    }

    #[Test]
    public function a_freeze_with_a_proven_worker_is_handed_to_the_queue_and_nothing_is_frozen_by_the_page(): void
    {
        $this->signIn();
        config(['queue.default' => 'database']);
        $this->provenWorker();
        Queue::fake();

        $page = Livewire::test(MigrationDashboard::class)->set('exportData.consistency', 'freeze');
        $this->start($page);

        $run = BackupRun::query()->exports()->firstOrFail();
        $this->assertSame(BackupRun::STATUS_PENDING, $run->status);
        Queue::assertPushed(RunExportJob::class);
        $this->assertNull(FreezeState::read(), 'only the worker engages the freeze, in its first step');

        // the page only shows it: polling never advances a freeze run
        $page->call('pollExport');
        $this->assertSame(BackupRun::STATUS_PENDING, $run->refresh()->status);
        $page->assertSee('Export #'.$run->getKey())->assertSee('A freeze export is carried out by a queue worker');
    }

    // ---- the password and the limit --------------------------------------------------------------------------------

    #[Test]
    public function a_wrong_password_starts_nothing(): void
    {
        $this->signIn();

        $this->start(Livewire::test(MigrationDashboard::class), password: 'wrong')->assertHasErrors(['exportData.password']);

        $this->assertSame(0, BackupRun::query()->exports()->count());
    }

    #[Test]
    public function after_five_wrong_passwords_even_the_right_one_is_refused_for_a_while(): void
    {
        $this->signIn();
        $page = Livewire::test(MigrationDashboard::class);

        for ($i = 0; $i < 5; $i++) {
            $this->start($page, password: 'bad'.$i);
        }

        $this->start($page)->assertHasErrors(['exportData.password']);
        $this->assertSame(0, BackupRun::query()->exports()->count());
    }

    #[Test]
    public function the_fourth_export_within_an_hour_is_refused_and_an_hour_later_it_is_allowed_again(): void
    {
        $this->signIn();
        $page = Livewire::test(MigrationDashboard::class);

        for ($i = 0; $i < 3; $i++) {
            $this->start($page)->assertHasNoErrors();
            $this->assertSame($i + 1, BackupRun::query()->exports()->count());
            $page->call('cancelExport')->call('dismissExport'); // let go of the shared lock so the next one can start
        }

        $this->start($page);
        $this->assertSame(3, BackupRun::query()->exports()->count(), 'the fourth start created nothing');
        $page->assertNotified('Too many exports for now');

        $this->travel(61)->minutes();
        $this->start($page);
        $this->assertSame(4, BackupRun::query()->exports()->count());
    }

    // ---- the wizard's steps -----------------------------------------------------------------------------------------

    #[Test]
    public function leaving_the_protect_step_with_a_weak_passphrase_is_blocked_and_with_a_good_one_counts_the_files(): void
    {
        $this->signIn();
        file_put_contents($this->dir.'/public/media/a.txt', 'hello');

        $page = Livewire::test(MigrationDashboard::class)
            ->set('exportData.preset', 'catalog_only')
            ->set('exportData.passphrase', 'short')
            ->set('exportData.passphrase_confirmation', 'short')
            ->goToWizardStep(5, 'exportForm')
            ->assertHasErrors(['exportData.passphrase']);
        $this->assertNull($page->get('exportFileCount'));

        $good = PassphraseInput::generate();
        $page->set('exportData.passphrase', $good)->set('exportData.passphrase_confirmation', $good)
            ->goToWizardStep(5, 'exportForm')
            ->assertHasNoErrors();

        $this->assertNotNull($page->get('exportFileCount'));
        $page->assertSee('This will export:')->assertSee('Passwords and API keys are left out');
    }

    // ---- a live copy, start to end ----------------------------------------------------------------------------------

    #[Test]
    public function a_live_copy_is_started_and_carried_to_the_end_by_the_pages_poll(): void
    {
        $this->signIn();
        $page = Livewire::test(MigrationDashboard::class);
        $this->start($page)->assertHasNoErrors();

        $run = BackupRun::query()->exports()->firstOrFail();
        $this->assertSame($run->getKey(), $page->get('exportRunId'));
        $this->assertNull($page->get('exportData.passphrase'), 'no secret stays in the page after the start');
        $page->assertSee('Export #'.$run->getKey());

        // The page's poll moves the run (each call redraws the whole page, which is slow and memory-hungry, so only a few go
        // through it); the rest is the same engine step the poll calls.
        $before = json_encode($run->refresh()->checkpoint());
        $page->call('pollExport')->call('pollExport')->call('pollExport');
        $this->assertNotSame($before, json_encode($run->refresh()->checkpoint()), 'the page poll advanced the export');

        for ($i = 0; $i < 3000 && ! $run->refresh()->isTerminal(); $i++) {
            app(ExportManager::class)->advance($run);
        }

        $page->call('pollExport'); // a run that is over is left alone, and the page shows the end
        $this->assertSame(BackupRun::STATUS_SUCCESS, $run->refresh()->status, (string) $run->error);
        $this->assertCount(1, glob($this->dir.'/exports/*.oeparts'));
        $page->assertSee('The package is ready');
        $this->assertStringContainsString('export.started', (string) file_get_contents($this->dir.'/history.ndjson'));
    }

    #[Test]
    public function a_running_export_can_be_cancelled_and_the_result_dismissed(): void
    {
        $this->signIn();
        $page = Livewire::test(MigrationDashboard::class);
        $this->start($page);

        $run = BackupRun::query()->exports()->firstOrFail();
        $page->call('cancelExport');
        $this->assertSame(BackupRun::STATUS_FAILED, $run->refresh()->status);

        $page->call('dismissExport')->assertSet('exportRunId', null);
    }

    #[Test]
    public function the_page_picks_up_an_export_that_is_already_running_when_it_opens(): void
    {
        $this->signIn();
        $this->start(Livewire::test(MigrationDashboard::class));
        $run = BackupRun::query()->exports()->firstOrFail();

        Livewire::test(MigrationDashboard::class)->assertSet('exportRunId', $run->getKey())->assertSee('Export #'.$run->getKey());
    }

    #[Test]
    public function a_running_export_shows_the_percent_and_message_the_engine_reported(): void
    {
        $this->signIn();
        $page = Livewire::test(MigrationDashboard::class);
        $this->start($page);
        $run = BackupRun::query()->exports()->firstOrFail();

        // Past the planning step the engine reports a real percent.
        $reported = app(ExportManager::class)->advance($run);
        for ($i = 0; $i < 3000 && ! $reported->done && $reported->percent <= 0; $i++) {
            $reported = app(ExportManager::class)->advance($run->refresh());
        }

        $this->assertFalse($reported->done);
        $shown = $page->instance()->exportProgress();
        $this->assertNotNull($shown);
        $this->assertSame((float) $reported->percent, (float) $shown->rawPercent, 'the panel shows the percent the engine reported, not zero');
        $this->assertGreaterThan(0, $shown->rawPercent);
        $this->assertSame((string) $reported->message, $shown->message);
        $this->assertNotSame('', $shown->message);
    }

    #[Test]
    public function a_failed_export_shows_its_real_error_text(): void
    {
        $this->signIn();
        $page = Livewire::test(MigrationDashboard::class);
        $this->start($page);
        $run = BackupRun::query()->exports()->firstOrFail();

        app(ExportManager::class)->fail($run, 'The disk is full, nothing was written.');

        $page->call('pollExport')->assertSee('The disk is full, nothing was written.');
    }

    // ---- reopening the shop -----------------------------------------------------------------------------------------

    #[Test]
    public function the_shop_can_be_reopened_from_the_page_and_the_freeze_is_released(): void
    {
        $this->signIn();
        app(ExportFreeze::class)->engage(1, 30);

        Livewire::test(MigrationDashboard::class)->call('reopenShop');

        $this->assertNull(FreezeState::read());
        $this->assertStringContainsString('export.unfrozen', (string) file_get_contents($this->dir.'/history.ndjson'));
    }

    #[Test]
    public function reopening_the_shop_also_stops_the_export_that_was_still_copying(): void
    {
        $this->signIn();
        $page = Livewire::test(MigrationDashboard::class);
        $this->start($page);
        $run = BackupRun::query()->exports()->firstOrFail();
        app(ExportFreeze::class)->engage((int) $run->getKey(), 30);

        $page->call('reopenShop');

        $this->assertSame(BackupRun::STATUS_FAILED, $run->refresh()->status, 'a copy taken while the shop is open would not be consistent');
        $this->assertNull(FreezeState::read());
        $this->assertFalse(app(BackupLock::class)->isLocked());
    }

    // ---- the typed form ----------------------------------------------------------------------------------------------

    #[Test]
    public function a_weak_or_mismatched_passphrase_is_refused_before_anything_starts(): void
    {
        $this->signIn();

        Livewire::test(MigrationDashboard::class)
            ->set('exportData.preset', 'catalog_only')
            ->set('exportData.passphrase', 'short')
            ->set('exportData.passphrase_confirmation', 'different')
            ->set('exportData.password', 'password')
            ->call('startExport')
            ->assertHasErrors();

        $this->assertSame(0, BackupRun::query()->exports()->count());
    }
}
