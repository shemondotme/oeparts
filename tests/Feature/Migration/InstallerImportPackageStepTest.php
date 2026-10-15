<?php

namespace Tests\Feature\Migration;

use App\Services\Migration\Installer\InstallerPackageUnlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\OpensInstallerPackages;
use Tests\TestCase;

/**
 * Step 3 of the installer's import branch: choosing a package from the inbox and opening it with its passphrase.
 *
 * The packages are real (the real writer, a cheap key derivation). The inbox, the wizard state, the wrong-passphrase counters
 * and the key escrow all live in a temporary folder, so nothing here can reach the developer's own storage.
 */
class InstallerImportPackageStepTest extends TestCase
{
    use OpensInstallerPackages;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareOpenedPackageTest();
    }

    protected function tearDown(): void
    {
        $this->cleanUpOpenedPackageTest();

        parent::tearDown();
    }

    // --- opening a package ---

    #[Test]
    public function the_right_passphrase_opens_the_package_and_shows_what_is_in_it(): void
    {
        $this->buildPackage();
        $this->ownerAtPackageStep();

        $this->openPackage()->assertRedirect(route('installer.import.package'));

        $page = $this->get('/install/import/package');
        $page->assertOk();
        $page->assertSee('Package opened');
        $page->assertSee('old.example');
        $page->assertSee('2.1.0');
        $page->assertSee('catalog');
        $page->assertSee('Choose another package');
    }

    #[Test]
    public function the_keys_go_to_the_escrow_and_nothing_secret_goes_to_the_wizard_file(): void
    {
        $this->buildPackage();
        $this->ownerAtPackageStep();

        $this->openPackage()->assertRedirect();

        $unlock = app(InstallerPackageUnlock::class)->current();
        $this->assertNotNull($unlock);
        $this->assertSame('shop.oeparts', $unlock['package']);
        $this->assertNotNull($this->escrow->get($unlock['escrow_id']), 'the keys are in the escrow');

        $stored = (string) file_get_contents($this->wizard()->path());
        $this->assertStringNotContainsString($this->passphrase, $stored);
        $this->assertStringContainsString($unlock['escrow_id'], $stored);
        $this->assertMatchesRegularExpression('/^wiz-[0-9a-f]{24}$/', $unlock['escrow_id']);
    }

    #[Test]
    public function the_passphrase_is_never_sent_back_to_the_browser(): void
    {
        $this->buildPackage();
        $this->ownerAtPackageStep();

        $response = $this->openPackage('shop.oeparts', 'definitely-wrong-passphrase-123');
        $response->assertRedirect(route('installer.import.package'));
        $response->assertSessionMissing('_old_input.passphrase');

        $this->get('/install/import/package')->assertDontSee('definitely-wrong-passphrase-123');
    }

    #[Test]
    public function a_wrong_passphrase_is_called_wrong_and_opens_nothing(): void
    {
        $this->buildPackage();
        $this->ownerAtPackageStep();

        $this->openPackage('shop.oeparts', 'MQ4X-9T2B-7HDW-K5RN-C8VE-3YGB')->assertRedirect();

        $page = $this->get('/install/import/package');
        $page->assertSee('That passphrase does not open this file.');
        $this->assertNull(app(InstallerPackageUnlock::class)->current());
        $this->assertSame([], glob($this->tmp.DIRECTORY_SEPARATOR.'escrow'.DIRECTORY_SEPARATOR.'*') ?: []);
    }

    #[Test]
    public function an_empty_passphrase_is_asked_for_again(): void
    {
        $this->buildPackage();
        $this->ownerAtPackageStep();

        $this->openPackage('shop.oeparts', '   ')->assertRedirect();

        $this->get('/install/import/package')->assertSee('a passphrase is required');
        $this->assertNull(app(InstallerPackageUnlock::class)->current());
    }

    #[Test]
    public function five_wrong_passphrases_lock_the_package_for_a_minute_even_against_the_right_one(): void
    {
        $this->buildPackage();
        $this->ownerAtPackageStep();

        for ($i = 0; $i < 5; $i++) {
            $this->openPackage('shop.oeparts', 'MQ4X-9T2B-7HDW-K5RN-C8VE-3YG'.$i)->assertRedirect();
        }

        $this->openPackage()->assertRedirect();
        $this->get('/install/import/package')->assertSee('Too many wrong passphrases were entered in a short time.');
        $this->assertNull(app(InstallerPackageUnlock::class)->current(), 'the right passphrase did not open it while locked');

        $this->clock += 61;
        $this->openPackage()->assertRedirect();
        $this->assertNotNull(app(InstallerPackageUnlock::class)->current());
    }

    // --- the connection ---

    #[Test]
    public function a_passphrase_is_refused_over_plain_http_on_a_real_host_before_anything_is_tried(): void
    {
        $this->buildPackage();
        $this->ownerAtPackageStep();

        $response = $this->callAsBrowser('POST', 'http://shop.example/install/import/package', ['package' => 'shop.oeparts', 'passphrase' => $this->passphrase]);
        $response->assertRedirect();

        $this->assertNull(app(InstallerPackageUnlock::class)->current());
        $this->assertSame([], glob($this->tmp.DIRECTORY_SEPARATOR.'throttle'.DIRECTORY_SEPARATOR.'*') ?: [], 'no attempt was counted');
        $this->assertSame([], glob($this->tmp.DIRECTORY_SEPARATOR.'escrow'.DIRECTORY_SEPARATOR.'*') ?: [], 'no key was derived or stored');

        $page = $this->callAsBrowser('GET', 'http://shop.example/install/import/package');
        $page->assertSee('will not accept a passphrase');
        $page->assertSee('php artisan oeparts:migrate:import');
        $page->assertDontSee('name="passphrase"', false);
    }

    #[Test]
    public function the_environment_switch_a_secure_request_and_a_loopback_host_all_allow_the_passphrase(): void
    {
        $this->buildPackage();
        $this->ownerAtPackageStep();

        // The default test address is http://localhost: a loopback host.
        $this->openPackage()->assertRedirect();
        $this->assertNotNull(app(InstallerPackageUnlock::class)->current());
        app(InstallerPackageUnlock::class)->forget();

        $this->callAsBrowser('POST', 'https://shop.example/install/import/package', ['package' => 'shop.oeparts', 'passphrase' => $this->passphrase], ['HTTPS' => 'on'])->assertRedirect();
        $this->assertNotNull(app(InstallerPackageUnlock::class)->current(), 'a secure request is allowed');
        app(InstallerPackageUnlock::class)->forget();

        config(['migration.installer.allow_http_passphrase' => true]);
        $this->callAsBrowser('POST', 'http://shop.example/install/import/package', ['package' => 'shop.oeparts', 'passphrase' => $this->passphrase])->assertRedirect();
        $this->assertNotNull(app(InstallerPackageUnlock::class)->current(), 'the operator accepted the risk in the environment');
    }

    // --- which file may be chosen ---

    #[Test]
    public function a_file_that_is_still_arriving_cannot_be_opened(): void
    {
        $path = $this->buildPackage('half.oeparts');
        touch($path, time() - 2);
        $this->ownerAtPackageStep();

        $this->openPackage('half.oeparts')->assertRedirect();

        $this->get('/install/import/package')->assertSee('The file looks incomplete.');
        $this->assertNull(app(InstallerPackageUnlock::class)->current());
    }

    #[Test]
    public function names_that_are_not_finished_inbox_packages_are_all_answered_as_not_found(): void
    {
        $this->buildPackage();
        $this->ownerAtPackageStep();

        foreach (['../shop.oeparts', 'nope.oeparts', 'shop.txt', '/etc/passwd', 'sub/dir.oeparts', ''] as $name) {
            $this->openPackage($name)->assertRedirect();
            $this->get('/install/import/package')->assertSee('We cannot find that file.');
            $this->assertNull(app(InstallerPackageUnlock::class)->current(), "refused: {$name}");
        }
    }

    #[Test]
    public function a_symbolic_link_in_the_inbox_is_not_a_package(): void
    {
        $real = $this->buildPackage('real.oeparts');
        if (! @symlink($real, $this->inboxDir().DIRECTORY_SEPARATOR.'link.oeparts')) {
            $this->markTestSkipped('Symbolic links cannot be created here.');
        }
        $this->ownerAtPackageStep();

        $list = $this->get('/install/import/package');
        $list->assertSee('real.oeparts');
        $list->assertDontSee('link.oeparts');

        $this->openPackage('link.oeparts')->assertRedirect();
        $this->get('/install/import/package')->assertSee('We cannot find that file.');
        $this->assertNull(app(InstallerPackageUnlock::class)->current());
    }

    #[Test]
    public function a_file_that_is_not_a_package_shows_why_in_the_list_and_cannot_be_chosen(): void
    {
        $this->dropPackage('photo.oeparts', 600);
        $this->buildPackage();
        $this->ownerAtPackageStep();

        $page = $this->get('/install/import/package');
        $page->assertSee('photo.oeparts');
        $page->assertSee('Not an OeParts file');

        $this->openPackage('photo.oeparts')->assertRedirect();
        $this->assertNull(app(InstallerPackageUnlock::class)->current());
    }

    #[Test]
    public function with_the_listing_switched_off_a_typed_name_still_works_and_other_names_stay_hidden(): void
    {
        config(['migration.installer.list_inbox' => false]);
        $this->buildPackage();
        $this->buildPackage('other.oeparts');
        $this->ownerAtPackageStep();

        $page = $this->get('/install/import/package');
        $page->assertDontSee('other.oeparts');
        $page->assertSee('name="package"', false);

        $this->openPackage('shop.oeparts')->assertRedirect();
        $this->assertNotNull(app(InstallerPackageUnlock::class)->current());
    }

    #[Test]
    public function with_the_listing_off_unknown_unfinished_and_wrong_guesses_get_the_same_answer_and_hide_the_folder(): void
    {
        config(['migration.installer.list_inbox' => false]);
        $this->buildPackage();
        $this->dropPackage('arriving.oeparts', 1);
        $this->dropPackage('junk.oeparts', 600);
        $this->ownerAtPackageStep();

        $page = $this->get('/install/import/package');
        $page->assertDontSee($this->inboxDir());
        $this->get('/install')->assertDontSee($this->inboxDir());

        $answers = [];

        foreach ([['nope.oeparts', $this->passphrase], ['arriving.oeparts', $this->passphrase], ['junk.oeparts', $this->passphrase], ['shop.oeparts', 'MQ4X-9T2B-7HDW-K5RN-C8VE-3YGB']] as [$name, $passphrase]) {
            $this->openPackage($name, $passphrase)->assertRedirect();
            $answers[] = (string) session('import_error.message');
            $this->assertNotSame('', end($answers));
        }

        $this->assertCount(1, array_unique($answers), 'one identical answer: '.json_encode($answers));
        $this->assertNull(app(InstallerPackageUnlock::class)->current());
    }

    #[Test]
    public function with_the_listing_off_guessing_names_is_throttled_like_guessing_passphrases(): void
    {
        config(['migration.installer.list_inbox' => false]);
        $this->ownerAtPackageStep();

        for ($i = 0; $i < 5; $i++) {
            $this->openPackage('nope'.$i.'.oeparts')->assertRedirect();
        }

        $this->openPackage('nope9.oeparts')->assertRedirect();
        $this->assertStringContainsString('Too many', (string) session('import_error.message'));
    }

    #[Test]
    public function an_idle_takeover_removes_the_previous_owners_keys_from_the_escrow(): void
    {
        $this->buildPackage();
        $this->ownerAtPackageStep();
        $this->openPackage()->assertRedirect();
        $this->assertNotEmpty(glob($this->tmp.DIRECTORY_SEPARATOR.'escrow'.DIRECTORY_SEPARATOR.'*'), 'the first owner keys are in the escrow');

        $this->travel(31)->minutes();
        $this->aStranger()->post('/install/import')->assertRedirect(route('installer.database'));

        $this->assertSame([], glob($this->tmp.DIRECTORY_SEPARATOR.'escrow'.DIRECTORY_SEPARATOR.'*') ?: [], 'nothing of the first owner lingers');
    }

    // --- choosing again, and time ---

    #[Test]
    public function choosing_another_package_closes_the_open_one_and_removes_its_keys(): void
    {
        $this->buildPackage();
        $this->ownerAtPackageStep();
        $this->openPackage()->assertRedirect();
        $id = app(InstallerPackageUnlock::class)->current()['escrow_id'];

        $this->post('/install/import/package/forget')->assertRedirect(route('installer.import.package'));

        $this->assertNull(app(InstallerPackageUnlock::class)->current());
        $this->assertNull($this->escrow->get($id), 'the keys are gone from the escrow');
        $this->get('/install/import/package')->assertSee('Open package');
    }

    #[Test]
    public function leaving_the_branch_removes_the_keys_too(): void
    {
        $this->buildPackage();
        $this->ownerAtPackageStep();
        $this->openPackage()->assertRedirect();
        $id = app(InstallerPackageUnlock::class)->current()['escrow_id'];

        $this->post('/install/import/cancel')->assertRedirect();

        $this->assertNull($this->escrow->get($id));
    }

    #[Test]
    public function the_server_reset_command_removes_the_keys_too(): void
    {
        $this->buildPackage();
        $this->ownerAtPackageStep();
        $this->openPackage()->assertRedirect();
        $id = app(InstallerPackageUnlock::class)->current()['escrow_id'];

        $this->artisan('oeparts:migrate:wizard-reset')->assertSuccessful();

        $this->assertNull($this->escrow->get($id));
    }

    #[Test]
    public function expired_keys_make_the_page_ask_for_the_passphrase_again(): void
    {
        $this->buildPackage();
        $this->ownerAtPackageStep();
        $this->openPackage()->assertRedirect();
        $id = app(InstallerPackageUnlock::class)->current()['escrow_id'];

        $this->escrow->delete($id);

        $this->assertNull(app(InstallerPackageUnlock::class)->current());
        $this->get('/install/import/package')->assertSee('Open package');
    }

    #[Test]
    public function opening_a_different_package_clears_what_was_chosen_for_the_first(): void
    {
        $this->buildPackage();
        $this->buildPackage('other.oeparts', ['source' => ['domain' => 'second.example']]);
        $this->ownerAtPackageStep();
        $this->openPackage()->assertRedirect();
        $this->wizard()->change(['choices' => ['launch' => 'live']]);

        $this->openPackage('other.oeparts')->assertRedirect();

        $this->assertSame([], $this->wizard()->choices());
        $this->assertSame('second.example', app(InstallerPackageUnlock::class)->current()['summary']['source_domain']);
    }

    // --- who may do this ---

    #[Test]
    public function only_the_owner_can_open_a_package_or_close_one(): void
    {
        $this->buildPackage();
        $nonce = $this->startBranch();
        $this->asBrowser($nonce)->withSession(['installer.db_configured' => true]);
        $this->openPackage()->assertRedirect();
        $before = (string) file_get_contents($this->wizard()->path());

        $this->aStranger()->withSession(['installer.db_configured' => true]);
        $this->openPackage()->assertForbidden();
        $this->post('/install/import/package/forget')->assertForbidden();

        $this->assertSame([], glob($this->tmp.DIRECTORY_SEPARATOR.'throttle'.DIRECTORY_SEPARATOR.'*') ?: [], 'a stranger consumed no attempt');
        $this->assertSame($before, (string) file_get_contents($this->wizard()->path()));
    }

    #[Test]
    public function the_package_step_has_no_upload_field_anywhere(): void
    {
        $this->buildPackage();
        $this->ownerAtPackageStep();

        $page = $this->get('/install/import/package');
        $page->assertDontSee('type="file"', false);
        $page->assertDontSee('multipart/form-data', false);
    }
}
