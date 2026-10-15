<?php

namespace Tests\Feature\Migration;

use App\Filament\Pages\System\MigrationDashboard;
use App\Filament\Widgets\Migration\GroupPickerSchema;
use App\Models\Admin;
use App\Security\PackageAudit;
use App\Services\Migration\Import\ImportInbox;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\ImportStatus;
use App\Services\Migration\KeyEscrow;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Transport\HostResolver;
use App\Services\Migration\Transport\IpClassifier;
use App\Services\Migration\Transport\SafeHttpFetcher;
use App\Services\Migration\Transport\TransferManager;
use App\Services\Migration\Transport\TransferStore;
use App\Services\Migration\Transport\UrlPolicy;
use App\Services\Migration\Ux\AdminImportFlow;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DrivesRealImports;
use Tests\Support\FakeHostResolver;
use Tests\Support\FakeRangeServer;
use Tests\TestCase;

/**
 * The Import tab: six wizard steps that open a REAL package, take the person's choices into a real run and hand over to the runner
 * page. Nothing here arms the import (the runner does that), so nothing on the site is changed; what is proved is what the page
 * asks, refuses and remembers, and that the hand-over carries the secret only in the address' fragment.
 *
 * The packages are real (the real writer, a cheap key derivation). The import folder, the runs, the keys and the history all live
 * in a temporary folder.
 */
class MigrationImportPanelTest extends TestCase
{
    use DrivesRealImports;
    use RefreshDatabase;

    private ImportStateStore $runs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->prepareRealImport();
        config(['migration.installer.stable_seconds' => 30, 'migration.import.advance_budget_seconds' => 10]);

        mkdir($this->workDir.'/_runs', 0700, true);
        $this->runs = new ImportStateStore($this->workDir.'/_runs');
        $this->app->instance(ImportStateStore::class, $this->runs);
        $this->app->instance(ImportInbox::class, new ImportInbox($this->workDir));
        $this->app->instance(KeyEscrow::class, new KeyEscrow($this->workDir.'/_escrow', (string) config('app.key')));
        $this->app->instance(PackageAudit::class, new PackageAudit($this->workDir.'/_history.ndjson'));
        $this->rebuildImportServices();
    }

    protected function tearDown(): void
    {
        $this->cleanUpRealImport();

        parent::tearDown();

        gc_collect_cycles(); // a full admin page is one big cycle of objects; free it before the next test starts
    }

    // ---- fixtures ---------------------------------------------------------------------------------------------------

    private function signIn(string $role = 'super_admin'): Admin
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole($role);
        RateLimiter::clear('reauth:'.$admin->getKey().'|127.0.0.1');
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    /** Somebody who may import and nothing else: the Export tab (which lists every part of a shop) is not on their page. */
    private function signInImportOnly(): Admin
    {
        $admin = $this->signIn();
        $admin->givePermissionTo('import migration');
        $admin->syncRoles([]);

        return $admin;
    }

    /** A real package that has been in the folder long enough to count as fully uploaded. */
    private function pkg(string $name = 'site.oeparts', array $meta = []): string
    {
        $path = $this->buildImportPackage($name, $meta);
        touch($path, time() - 600);

        return $path;
    }

    private function page()
    {
        return Livewire::test(MigrationDashboard::class);
    }

    /** Press "Next" on step $step - 1, the way the wizard's button does. */
    private function next($page, int $step)
    {
        return $page->goToWizardStep($step, 'importForm');
    }

    /** The page after the package was chosen and opened with the right passphrase: the person is on step 3. */
    private function opened(string $package = 'site.oeparts')
    {
        $page = $this->page()->set('importData.source', 'inbox')->set('importData.package', $package);
        $this->next($page, 2)->assertHasNoErrors();
        $page->set('importData.passphrase', $this->passphrase);
        $this->next($page, 3)->assertHasNoErrors();

        return $page;
    }

    /** The page on step 5, with the choices that make the checks pass for a package of this very site. */
    private function checked(string $package = 'site.oeparts', string $launch = 'live')
    {
        $page = $this->opened($package);
        $this->next($page, 4)->assertHasNoErrors();
        $page->set('importData.launch', $launch)->set('importData.site_url', 'http://new-shop.example');
        $this->next($page, 5)->assertHasNoErrors();

        return $page;
    }

    private function runId($page): string
    {
        return (string) $page->get('importRunId');
    }

    // ---- who sees it --------------------------------------------------------------------------------------------------

    #[Test]
    public function the_import_tab_is_for_admins_who_may_import(): void
    {
        $admin = $this->signIn();
        $admin->givePermissionTo('import migration');
        $admin->syncRoles([]);

        $this->page()->assertSee('Only import a package you trust')->assertSee('Where is the file');
    }

    #[Test]
    public function an_admin_who_may_only_export_does_not_get_the_import_tab(): void
    {
        $admin = $this->signIn();
        $admin->givePermissionTo('export migration');
        $admin->syncRoles([]);

        $this->page()->assertDontSee('Only import a package you trust')->assertSee('What to export');
    }

    #[Test]
    public function an_admin_who_may_only_export_cannot_drive_the_import_methods(): void
    {
        $admin = $this->signIn();
        $admin->givePermissionTo('export migration');
        $admin->syncRoles([]);

        foreach (['confirmImport', 'cancelImport', 'pollImport', 'pollImportFetch', 'startImportFetch', 'discardOtherRun'] as $method) {
            $this->page()->call($method)->assertForbidden();
        }

        $this->assertSame([], $this->runs->all());
    }

    // ---- step 1: where is the file ------------------------------------------------------------------------------------------

    #[Test]
    public function the_folder_is_listed_and_a_file_that_is_still_arriving_cannot_be_chosen(): void
    {
        $this->signIn();
        $this->pkg('ready.oeparts');
        $fresh = $this->buildImportPackage('arriving.oeparts'); // modified just now: the upload may still be going on
        $this->assertFileExists($fresh);

        $this->page()
            ->assertSee('ready.oeparts')
            ->assertSee('arriving.oeparts')
            ->assertSee('still uploading')
            ->set('importData.package', 'arriving.oeparts')
            ->goToWizardStep(2, 'importForm')
            ->assertHasErrors(['importData.package']);
    }

    #[Test]
    public function with_nothing_in_the_folder_the_step_says_where_to_put_the_file(): void
    {
        $this->signIn();

        $this->page()->assertSee('No package found yet');
    }

    #[Test]
    public function the_next_button_needs_a_package(): void
    {
        $this->signIn();

        $this->page()->goToWizardStep(2, 'importForm')->assertHasErrors(['importData.package']);
    }

    // ---- step 2: open it --------------------------------------------------------------------------------------------------

    #[Test]
    public function the_right_passphrase_opens_the_package_and_the_next_step_says_what_is_in_it(): void
    {
        $this->signIn();
        $this->pkg();

        $page = $this->opened();

        $this->assertNotNull($page->get('importRunId'));
        $this->assertNotNull($page->get('importToken'));
        $this->assertNull($page->get('importData.passphrase'), 'the passphrase is not kept once it has opened the package');
        $page->assertSee('old.example')->assertSee('Site basics')->assertSee('Products');

        $state = $this->runs->find($this->runId($page));
        $this->assertSame('admin', $state->options['door']);
        $this->assertSame(ImportStatus::AwaitingConfirmation, $state->status);
        $this->assertSame(hash('sha256', (string) $page->get('importToken')), $state->tokenHash, 'the server keeps only a hash of the runner token');
    }

    #[Test]
    public function a_wrong_passphrase_is_a_field_error_and_leaves_no_run_behind(): void
    {
        $this->signIn();
        $this->pkg();

        $page = $this->page()->set('importData.package', 'site.oeparts');
        $this->next($page, 2);
        $page->set('importData.passphrase', 'WRONG-0000-1111-2222-3333-4444');
        $this->next($page, 3)->assertHasErrors(['importData.passphrase']);

        $this->assertNull($page->get('importRunId'));
        $this->assertSame([], $this->runs->all());
    }

    #[Test]
    public function after_five_wrong_passphrases_even_the_right_one_is_refused_for_a_while(): void
    {
        $this->signIn();
        $this->pkg();
        $page = $this->page()->set('importData.package', 'site.oeparts');
        $this->next($page, 2);

        for ($i = 0; $i < 5; $i++) {
            $page->set('importData.passphrase', 'WRONG-0000-1111-2222-3333-444'.$i);
            $this->next($page, 3);
        }

        $page->set('importData.passphrase', $this->passphrase);
        $this->next($page, 3)->assertHasErrors(['importData.passphrase']);
        $this->assertNull($page->get('importRunId'));
    }

    #[Test]
    public function a_file_that_is_not_a_package_is_refused_with_a_plain_sentence(): void
    {
        $this->signIn();
        file_put_contents($this->workDir.'/fake.oeparts', 'this is not a package, only some text, but long enough to be read');
        touch($this->workDir.'/fake.oeparts', time() - 600);

        $page = $this->page()->set('importData.package', 'fake.oeparts');
        $this->next($page, 2);
        $page->set('importData.passphrase', $this->passphrase);
        $this->next($page, 3)->assertHasErrors(['importData.passphrase']);

        $this->assertSame([], $this->runs->all());
    }

    #[Test]
    public function choosing_another_package_after_opening_one_throws_the_first_run_away(): void
    {
        $this->signIn();
        $this->pkg('one.oeparts');
        $this->pkg('two.oeparts');

        $page = $this->opened('one.oeparts');
        $first = $this->runId($page);

        $page->set('importData.package', 'two.oeparts');
        $this->next($page, 2);

        $this->assertNull($page->get('importRunId'));
        $this->assertNull($this->runs->find($first), 'the half-prepared run of the first package was discarded');
    }

    // ---- step 3: what to bring ----------------------------------------------------------------------------------------------

    #[Test]
    public function the_picker_offers_only_what_the_package_holds_with_the_exports_words(): void
    {
        $this->signInImportOnly();
        $this->pkg(); // core, catalog, media
        $page = $this->opened()->set('importData.preset', 'custom');

        $page->assertSee('Products')->assertSee('Media library')->assertDontSee('Orders and payments')->assertDontSee('Invoices');
    }

    #[Test]
    public function the_export_and_the_import_pickers_render_identical_options(): void
    {
        $all = GroupPickerSchema::options(null);
        $held = ['core', 'catalog', 'media'];

        $this->assertArrayHasKey('sales', $all, 'the export offers every part a site can have');
        $this->assertSame(array_intersect_key($all, array_flip($held)), GroupPickerSchema::options(['catalog', 'media']), 'the import offers the same words, in the same order, for the parts the package holds, and site basics always');
        $this->assertSame(array_intersect_key(GroupPickerSchema::descriptions(null), array_flip($held)), GroupPickerSchema::descriptions(['catalog', 'media']));
        $this->assertSame(['core' => 'Site basics', 'catalog' => 'Products'], GroupPickerSchema::options(['catalog']));
    }

    #[Test]
    public function a_choice_the_package_cannot_serve_is_refused_on_the_field(): void
    {
        $this->signIn();
        $this->pkg();
        $page = $this->opened()->set('importData.preset', 'custom')->set('importData.groups', ['core', 'sales']);

        $this->next($page, 4)->assertHasErrors();
        $this->assertTrue(
            collect($page->errors()->keys())->contains(fn (string $key): bool => str_starts_with($key, 'importData.groups')),
            'the complaint is on the parts field (on the part the package does not hold)',
        );
        $this->assertNull($page->get('importGroups') ?: null);
    }

    #[Test]
    public function everything_the_package_holds_is_the_default_choice_and_is_kept_in_a_locked_property(): void
    {
        $this->signIn();
        $this->pkg();
        $page = $this->opened();

        $this->next($page, 4)->assertHasNoErrors();

        $this->assertEqualsCanonicalizing(['core', 'catalog', 'media'], $page->get('importGroups'));
    }

    // ---- step 4: live or test -----------------------------------------------------------------------------------------------

    #[Test]
    public function live_or_test_has_no_default_and_must_be_answered(): void
    {
        $this->signIn();
        $this->pkg();
        $page = $this->opened();
        $this->next($page, 4);

        $this->assertNull($page->get('importData.launch'));
        $this->next($page, 5)->assertHasErrors(['importData.launch']);
    }

    #[Test]
    public function an_https_address_is_refused_when_the_page_was_opened_over_plain_http(): void
    {
        $this->signIn();
        $this->pkg();
        $page = $this->opened();
        $this->next($page, 4);

        $page->set('importData.launch', 'live')->set('importData.site_url', 'https://new-shop.example');
        $this->next($page, 5)->assertHasErrors(['importData.site_url']);

        $page->set('importData.site_url', 'http://new-shop.example/shop');
        $this->next($page, 5)->assertHasErrors(['importData.site_url']);
    }

    #[Test]
    public function the_address_and_the_choice_go_into_the_run_before_the_checks_run(): void
    {
        $this->signIn();
        $this->pkg();
        $page = $this->checked('site.oeparts', 'staging');

        $options = $this->runs->find($this->runId($page))->options;
        $this->assertSame('staging', $options['launch']);
        $this->assertSame('http://new-shop.example', $options['url']);
        $this->assertArrayNotHasKey('mail_allowlist', $options);
    }

    // ---- step 5: the checks -------------------------------------------------------------------------------------------------

    #[Test]
    public function the_checks_are_listed_in_words_and_a_clean_site_can_go_on(): void
    {
        $this->signIn();
        $this->pkg();
        $page = $this->checked();

        $page->assertSee('What the checks say')->assertSee('[OK]');
        $this->next($page, 6)->assertHasNoErrors();
        $this->assertArrayHasKey('core', $page->get('importEmptied'), 'what the import empties is counted when the checks are accepted');
    }

    #[Test]
    public function a_package_from_another_release_blocks_the_step_and_says_so(): void
    {
        $this->signIn();
        $this->pkg('old.oeparts', ['migrations' => array_slice($this->ownMigrations(), 0, -3)]);
        $page = $this->checked('old.oeparts');

        $page->assertSee('[Problem]')->assertSee('Something blocks this import');
        $this->next($page, 6);
        $page->assertNotified('Something blocks this import');
    }

    // ---- step 6: confirm ------------------------------------------------------------------------------------------------------

    private function confirmWith($page, string $typed, string $password)
    {
        return $page->set('importData.typed', $typed)->set('importData.password', $password)->call('confirmImport');
    }

    #[Test]
    public function replace_must_be_typed_and_nothing_is_confirmed_without_it(): void
    {
        $this->signIn();
        $this->pkg();
        $page = $this->checked();
        $this->next($page, 6);

        $this->confirmWith($page, 'replace', 'password')->assertHasErrors(['importData.typed'])->assertNoRedirect();
        $this->confirmWith($page, '', 'password')->assertHasErrors(['importData.typed'])->assertNoRedirect();

        $this->assertFalse($this->runs->find($this->runId($page))->flag('confirmed'));
    }

    #[Test]
    public function a_wrong_password_confirms_nothing_and_five_of_them_lock_the_right_one_out(): void
    {
        $this->signIn();
        $this->pkg();
        $page = $this->checked();
        $this->next($page, 6);

        for ($i = 0; $i < 5; $i++) {
            $this->confirmWith($page, 'REPLACE', 'wrong'.$i)->assertHasErrors(['importData.password']);
        }

        $this->confirmWith($page, 'REPLACE', 'password')->assertHasErrors(['importData.password'])->assertNoRedirect();
        $this->assertFalse($this->runs->find($this->runId($page))->flag('confirmed'));
    }

    #[Test]
    public function the_right_words_confirm_the_run_and_hand_over_to_the_runner_with_the_token_in_the_fragment(): void
    {
        $this->signIn();
        $this->pkg();
        $page = $this->checked();
        $this->next($page, 6);
        $id = $this->runId($page);
        $token = (string) $page->get('importToken');

        $this->confirmWith($page, 'REPLACE', 'password')->assertHasNoErrors()->assertRedirect(url('/migrate/run/'.$id).'#t='.$token);

        $state = $this->runs->find($id);
        $this->assertTrue($state->flag('confirmed'));
        $this->assertSame('admin', $state->options['door']);
        $this->assertEqualsCanonicalizing(['core', 'catalog', 'media'], $state->options['groups']);
        $this->assertNull($page->get('importToken'), 'the secret leaves the page with the redirect');

        $history = (string) file_get_contents($this->workDir.'/_history.ndjson');
        $this->assertStringContainsString('import.confirmed', $history);
        $this->assertStringNotContainsString($token, $history, 'the durable line never carries the runner token');
    }

    #[Test]
    public function a_confirmed_run_can_no_longer_be_changed_by_the_form_or_the_flow(): void
    {
        $this->signIn();
        $this->pkg();
        $page = $this->checked();
        $this->next($page, 6);
        $id = $this->runId($page);

        $this->confirmWith($page, 'REPLACE', 'password')->assertHasNoErrors();
        $this->assertNull($page->get('importRunId'), 'the form lets go of the confirmed run');

        $before = $this->runs->find($id)->options;

        try {
            app(AdminImportFlow::class)->check($id, ['url' => 'http://evil.example', 'launch' => 'staging']);
            $this->fail('a confirmed run must refuse new options');
        } catch (MigrationException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($before, $this->runs->find($id)->options, 'the options the person agreed to are untouched');
    }

    #[Test]
    public function a_site_that_has_been_used_shows_the_red_callout_with_its_counts_and_needs_the_warning_ticked(): void
    {
        $this->signIn();
        $this->plantRow('orders', ['order_number' => 'LIVE-1']);
        $this->pkg();
        $page = $this->checked();
        $this->next($page, 6);

        $page->assertSee('This site already has data')->assertSee('1 orders and payments')->assertSee('All of it will be deleted and replaced');
        $this->assertNotSame([], $page->get('importEmptied'), 'the confirm step lists the rows that will be emptied, per part');

        // typed REPLACE and the right password are not enough: the warning of a used site has to be read
        $this->confirmWith($page, 'REPLACE', 'password')->assertHasErrors(['importData.acks'])->assertNoRedirect();
        $this->assertFalse($this->runs->find($this->runId($page))->flag('confirmed'));

        $id = $this->runId($page);
        $page->set('importData.acks', ['target_populated']);
        $this->confirmWith($page, 'REPLACE', 'password')->assertHasNoErrors()->assertRedirectContains('/migrate/run/');
        $this->assertTrue($this->runs->find($id)->flag('confirmed'));
    }

    // ---- starting over and the other runs -------------------------------------------------------------------------------------

    #[Test]
    public function start_over_throws_the_prepared_run_away_and_empties_the_form(): void
    {
        $this->signIn();
        $this->pkg();
        $page = $this->checked();
        $id = $this->runId($page);

        $page->call('cancelImport');

        $this->assertNull($page->get('importRunId'));
        $this->assertNull($page->get('importToken'));
        $this->assertNull($this->runs->find($id));
        $this->assertNull($page->get('importData.package'));
    }

    #[Test]
    public function a_confirmed_run_cannot_be_thrown_away_from_the_page(): void
    {
        $this->signIn();
        $this->pkg();
        $page = $this->checked();
        $this->next($page, 6);
        $id = $this->runId($page);
        $this->confirmWith($page, 'REPLACE', 'password');

        $this->page()->call('discardOtherRun');

        $this->assertNotNull($this->runs->find($id));
    }

    #[Test]
    public function a_half_prepared_run_of_a_closed_tab_is_shown_and_can_be_discarded(): void
    {
        $this->signIn();
        $this->pkg();
        $orphan = $this->runId($this->opened()); // a first tab, never finished

        $second = $this->page()->assertSee('half-prepared import')->assertSee($orphan);
        $second->call('discardOtherRun');

        $this->assertNull($this->runs->find($orphan));
        $this->page()->assertDontSee('half-prepared import');
    }

    #[Test]
    public function a_run_that_is_not_the_admin_panels_is_shown_with_the_command_to_finish_it(): void
    {
        $this->signIn();
        $this->runs->create('import', ['options' => ['door' => 'cli'], 'initiator' => ['via' => 'command line']]);

        $this->page()->assertSee('Another import is on this server')->assertSee('oeparts:migrate:import --resume');
    }

    // ---- from this computer ------------------------------------------------------------------------------------------------------

    #[Test]
    public function a_small_package_from_this_computer_is_saved_into_the_folder_and_opened_like_any_other(): void
    {
        Storage::fake('local');
        $this->signIn();
        $path = $this->pkg('mine.oeparts');
        $content = (string) file_get_contents($path);
        unlink($path); // the only copy is the one in the browser

        $page = $this->page()
            ->set('importData.source', 'computer')
            ->set('importData.upload', UploadedFile::fake()->createWithContent('mine.oeparts', $content));
        $this->next($page, 2)->assertHasNoErrors();

        $this->assertSame('mine.oeparts', $page->get('importData.package'));
        $this->assertSame($content, file_get_contents($this->workDir.'/mine.oeparts'));
        $this->assertStringContainsString('package.uploaded', (string) file_get_contents($this->workDir.'/_history.ndjson'));

        $page->set('importData.passphrase', $this->passphrase);
        $this->next($page, 3)->assertHasNoErrors();
        $this->assertNotNull($page->get('importRunId'));
    }

    #[Test]
    public function a_file_that_is_not_a_package_is_refused_on_the_upload_field_and_leaves_nothing_in_the_folder(): void
    {
        Storage::fake('local');
        $this->signIn();

        $page = $this->page()
            ->set('importData.source', 'computer')
            ->set('importData.upload', UploadedFile::fake()->createWithContent('notes.oeparts', 'just some text that is not a package'));
        $this->next($page, 2)->assertHasErrors(['importData.upload']);

        $this->assertSame([], glob($this->workDir.'/*.oeparts'));
        $this->assertNull($page->get('importData.package'));
    }

    #[Test]
    public function a_file_above_the_limit_is_refused_with_the_instructions(): void
    {
        Storage::fake('local');
        $this->signIn();
        config(['migration.upload.small_max_mb' => 1]);

        $page = $this->page()
            ->set('importData.source', 'computer')
            ->set('importData.upload', UploadedFile::fake()->createWithContent('big.oeparts', 'OEPARTS1'.str_repeat('x', 1024 * 1024 + 10)));
        $this->next($page, 2)->assertHasErrors(['importData.upload']);

        $this->assertSame([], glob($this->workDir.'/*.oeparts'));
        $page->assertSee('FTP or SFTP');
    }

    #[Test]
    public function with_this_computer_chosen_the_next_button_needs_the_file(): void
    {
        $this->signIn();

        $this->next($this->page()->set('importData.source', 'computer'), 2)->assertHasErrors(['importData.upload']);
    }

    #[Test]
    public function the_upload_field_is_only_on_the_page_when_this_computer_is_the_way(): void
    {
        $this->signInImportOnly();

        $this->page()->assertDontSee('The package file')->set('importData.source', 'computer')->assertSee('The package file')->assertSee('up to 10 MB');
    }

    #[Test]
    public function an_admin_who_may_not_import_cannot_save_an_upload(): void
    {
        Storage::fake('local');
        $admin = $this->signIn();
        $admin->givePermissionTo('export migration');
        $admin->syncRoles([]);

        $page = $this->page()
            ->set('importData.source', 'computer')
            ->set('importData.upload', UploadedFile::fake()->createWithContent('mine.oeparts', 'OEPARTS1'.str_repeat('x', 2000)));
        $this->next($page, 2)->assertForbidden();

        $this->assertSame([], glob($this->workDir.'/*.oeparts'));
    }

    // ---- from a link ------------------------------------------------------------------------------------------------------------

    private function serveFromLink(string $file): void
    {
        $server = new FakeRangeServer((string) file_get_contents($file));
        $resolver = new FakeHostResolver;
        mkdir($this->workDir.'/link-inbox', 0700, true);

        $this->app->instance(HostResolver::class, $resolver);
        $this->app->instance(TransferStore::class, new TransferStore($this->workDir.'/link-transfers'));
        $this->app->instance(ImportInbox::class, new ImportInbox($this->workDir.'/link-inbox'));
        $this->app->instance(SafeHttpFetcher::class, new SafeHttpFetcher(new UrlPolicy($resolver, new IpClassifier), $server->handler()));
        $this->app->forgetInstance(TransferManager::class);
        $this->rebuildImportServices();
    }

    #[Test]
    public function a_link_that_is_not_https_is_refused_on_the_field(): void
    {
        $this->signIn();

        $this->page()
            ->set('importData.source', 'link')
            ->set('importData.url', 'http://files.example.com/site.oeparts')
            ->call('startImportFetch')
            ->assertHasErrors(['importData.url'])
            ->assertSet('transferId', null);
    }

    #[Test]
    public function a_link_must_be_downloaded_before_the_next_button_works(): void
    {
        $this->signIn();

        $this->page()->set('importData.source', 'link')->goToWizardStep(2, 'importForm')->assertHasErrors(['importData.url']);
    }

    #[Test]
    public function a_package_from_a_link_is_downloaded_into_the_folder_and_then_opened_like_any_other(): void
    {
        $this->signIn();
        $package = $this->pkg('remote.oeparts');
        $this->serveFromLink($package);

        $page = $this->page()
            ->set('importData.source', 'link')
            ->set('importData.url', 'https://files.example.com/pkg/remote.oeparts')
            ->set('importData.sha256', hash_file('sha256', $package))
            ->call('startImportFetch')
            ->assertHasNoErrors();

        for ($i = 0; $i < 40 && $page->get('importData.package') === null; $i++) {
            $page->call('pollImportFetch');
        }

        $this->assertSame('remote.oeparts', $page->get('importData.package'));
        $this->assertNull($page->get('importData.url'), 'the link is not kept in the page once the transfer has it');
        $this->assertFileEquals($package, $this->workDir.'/link-inbox/remote.oeparts');

        $this->next($page, 2)->assertHasNoErrors();
        $page->set('importData.passphrase', $this->passphrase);
        $this->next($page, 3)->assertHasNoErrors();
        $this->assertNotNull($page->get('importRunId'));
    }
}
