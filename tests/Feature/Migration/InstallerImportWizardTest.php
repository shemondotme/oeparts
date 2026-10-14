<?php

namespace Tests\Feature\Migration;

use App\Services\Install\InstallManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DrivesInstallerImport;
use Tests\TestCase;

/**
 * The installer's "import an existing site" branch: the two start cards, the step bar, and the one-browser ownership.
 *
 * Everything that would otherwise touch the real server (the wizard state file, the package inbox) lives in a temporary
 * folder bound into the container, so a test can never read, write or delete the developer's own storage/app/import.
 */
class InstallerImportWizardTest extends TestCase
{
    use DrivesInstallerImport;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareInstallerImportTest();
    }

    protected function tearDown(): void
    {
        $this->cleanUpInstallerImportTest();

        parent::tearDown();
    }

    // --- step 1: the two cards ---

    #[Test]
    public function step_one_offers_a_new_install_and_an_import_with_the_default_six_step_bar(): void
    {
        $response = $this->get('/install');

        $response->assertOk();
        $response->assertSee('How do you want to start?');
        $response->assertSee('Install a new site');
        $response->assertSee('Import an existing site (.oeparts file)');
        $response->assertSee(route('installer.database'), false);
        $response->assertSee(route('installer.import.mode'), false);

        // The normal install keeps its own steps: no Package step, no import header.
        $response->assertSee('Site Settings');
        $response->assertSee('Email Setup');
        $response->assertDontSee('Package');
        $response->assertDontSee('Installer · Import');
        $response->assertDontSee('We found');
    }

    #[Test]
    public function step_one_shows_a_badge_only_for_a_package_that_has_finished_uploading(): void
    {
        $this->dropPackage('shop.oeparts', 600);
        $this->dropPackage('still-copying.oeparts', 1);
        $this->dropPackage('notes.txt', 600);

        $response = $this->get('/install');

        $response->assertSee('We found 1 package in ');
    }

    #[Test]
    public function step_one_shows_no_badge_when_the_inbox_listing_is_switched_off(): void
    {
        config(['migration.installer.list_inbox' => false]);
        $this->dropPackage('shop.oeparts', 600);

        $this->get('/install')->assertDontSee('We found');
    }

    // --- starting the branch ---

    #[Test]
    public function opening_the_import_page_creates_nothing(): void
    {
        $response = $this->get('/install/import');

        $response->assertOk();
        $response->assertSee('Start the import');
        $this->assertFalse($this->wizard()->exists());
        $this->assertNull($this->cookieOf($response));
    }

    #[Test]
    public function starting_it_binds_the_branch_to_this_browser_and_stores_only_a_hash(): void
    {
        $response = $this->post('/install/import');

        $response->assertRedirect(route('installer.database'));

        $cookie = $this->cookieOf($response);
        $this->assertNotNull($cookie);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $cookie->getValue(), 'a plain 256-bit nonce, not an encrypted payload');
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('strict', $cookie->getSameSite());
        $this->assertSame('/install', $cookie->getPath());

        $stored = (string) file_get_contents($this->wizard()->path());
        $this->assertStringNotContainsString($cookie->getValue(), $stored);
        $this->assertStringContainsString(hash('sha256', $cookie->getValue()), $stored);
        $this->assertTrue($this->wizard()->owns($cookie->getValue()));
    }

    #[Test]
    public function the_cookie_is_marked_secure_when_the_request_came_over_https(): void
    {
        $response = $this->call('POST', 'https://localhost/install/import', [], [], [], ['HTTPS' => 'on']);

        $cookie = $this->cookieOf($response);
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
    }

    #[Test]
    public function the_state_file_is_private_to_the_owner(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('POSIX file modes do not apply on Windows.');
        }

        $this->startBranch();

        $this->assertSame('0600', substr(sprintf('%o', fileperms($this->wizard()->path())), -4));
    }

    #[Test]
    public function the_owner_sees_the_seven_step_import_bar_on_the_first_two_steps(): void
    {
        $nonce = $this->startBranch();

        foreach (['/install', '/install/database'] as $url) {
            $response = $this->asBrowser($nonce)->get($url);

            $response->assertOk();
            $response->assertSee('Installer · Import');
            $response->assertSee('Package');
            $response->assertSee('Choose');
            $response->assertSee('Check');
            $response->assertSee('Done');
            $response->assertDontSee('Site Settings');
        }
    }

    // --- ownership ---

    #[Test]
    public function a_second_browser_gets_one_plain_page_and_can_read_or_change_nothing(): void
    {
        $nonce = $this->startBranch();
        $this->dropPackage('private-name.oeparts', 600);
        $before = (string) file_get_contents($this->wizard()->path());

        foreach (['/install/import/package'] as $url) {
            $response = $this->aStranger()->withSession(['installer.db_configured' => true])->get($url);

            $response->assertForbidden();
            $response->assertSee('Someone else started this install in another browser.');
            $response->assertDontSee('private-name.oeparts');
        }

        // It cannot start the branch for itself, nor leave it for the owner.
        $this->aStranger()->post('/install/import')->assertForbidden();
        $this->aStranger()->get('/install/import')->assertForbidden();
        $this->aStranger()->post('/install/import/cancel')->assertForbidden();

        // A cookie that is not the owner's is the same as no cookie.
        $this->asBrowser(str_repeat('a', 64))->get('/install/import/package')->assertForbidden();

        $this->assertSame($before, (string) file_get_contents($this->wizard()->path()), 'nothing the stranger did changed the branch');
        $this->assertTrue($this->wizard()->owns($nonce));
    }

    #[Test]
    public function a_browser_that_never_started_the_branch_is_sent_to_the_start_page_when_nobody_holds_it(): void
    {
        $this->aStranger()->get('/install/import/package')->assertRedirect(route('installer.import.mode'));
    }

    #[Test]
    public function another_browser_can_take_over_only_after_the_owner_was_idle_for_thirty_minutes(): void
    {
        $first = $this->startBranch();

        $this->travel(29)->minutes();
        $this->aStranger()->post('/install/import')->assertForbidden();

        $this->travel(2)->minutes();
        $second = $this->aStranger()->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->post('/install/import');
        $second->assertRedirect(route('installer.database'));

        $this->assertNotSame($first, $this->cookieOf($second)->getValue());
        $this->assertFalse($this->wizard()->owns($first), 'the old owner lost the branch');
        $this->assertTrue($this->wizard()->owns($this->cookieOf($second)->getValue()));
    }

    #[Test]
    public function every_page_view_by_the_owner_restarts_the_idle_clock(): void
    {
        $nonce = $this->startBranch();

        $this->travel(25)->minutes();
        $this->asBrowser($nonce)->withSession(['installer.db_configured' => true])->get('/install/import/package')->assertOk();

        $this->travel(25)->minutes();
        $this->aStranger()->post('/install/import')->assertForbidden();
    }

    #[Test]
    public function the_server_command_frees_the_branch_and_the_old_cookie_stops_working(): void
    {
        $nonce = $this->startBranch();

        $this->artisan('oeparts:migrate:wizard-reset')->assertSuccessful();

        $this->assertFalse($this->wizard()->exists());
        $this->assertFalse($this->wizard()->owns($nonce));
        $this->asBrowser($nonce)->get('/install/import/package')->assertRedirect(route('installer.import.mode'));

        $this->artisan('oeparts:migrate:wizard-reset')->expectsOutputToContain('Nothing to reset')->assertSuccessful();
    }

    #[Test]
    public function a_torn_state_file_is_treated_as_nobody_having_started(): void
    {
        file_put_contents($this->wizard()->path(), '{"nonce_hash": "abc');

        $this->assertFalse($this->wizard()->exists());
        $this->assertTrue($this->wizard()->isIdle());
        $this->assertNotNull($this->wizard()->claim('10.0.0.1'));
    }

    #[Test]
    public function two_claims_cannot_both_win(): void
    {
        $this->assertNotNull($this->wizard()->claim('10.0.0.1'));
        $this->assertNull($this->wizard()->claim('10.0.0.2'));
    }

    #[Test]
    public function the_owner_can_start_again_without_losing_the_branch(): void
    {
        $nonce = $this->startBranch();

        $response = $this->asBrowser($nonce)->post('/install/import');

        $response->assertRedirect();
        $this->assertNull($this->cookieOf($response), 'no new nonce: the one the owner holds still works');
        $this->assertTrue($this->wizard()->owns($nonce));
    }

    #[Test]
    public function an_empty_or_oversized_cookie_never_owns_the_branch(): void
    {
        $this->startBranch();

        $this->assertFalse($this->wizard()->owns(null));
        $this->assertFalse($this->wizard()->owns(''));
        $this->assertFalse($this->wizard()->owns(str_repeat('a', 5000)));
    }

    // --- leaving the branch ---

    #[Test]
    public function the_owner_can_leave_for_a_normal_install(): void
    {
        $nonce = $this->startBranch();

        $response = $this->asBrowser($nonce)->post('/install/import/cancel', ['next' => 'database']);

        $response->assertRedirect(route('installer.database'));
        $this->assertFalse($this->wizard()->exists());
        $cookie = $this->cookieOf($response);
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isCleared(), 'the cookie is removed');

        $this->aStranger()->get('/install/database')->assertSee('Site Settings');
    }

    #[Test]
    public function leaving_without_a_destination_returns_to_the_first_step(): void
    {
        $nonce = $this->startBranch();

        $this->asBrowser($nonce)->post('/install/import/cancel')->assertRedirect(route('installer.index'));
    }

    // --- step 2 hands over to step 3 ---

    #[Test]
    public function a_successful_database_step_continues_to_the_package_step_in_the_branch(): void
    {
        $nonce = $this->startBranch();

        DB::shouldReceive('purge')->with('mysql')->andReturnNull();
        DB::shouldReceive('reconnect')->with('mysql')->andReturnNull();
        DB::shouldReceive('connection')->with('mysql')->andReturnSelf();
        DB::shouldReceive('getPdo')->andReturn(new \stdClass);
        $this->partialMock(InstallManager::class, function ($mock): void {
            $mock->shouldReceive('updateEnvFile')->once();
        });

        $response = $this->asBrowser($nonce)->post('/install/database', [
            'db_host' => '127.0.0.1',
            'db_port' => '3306',
            'db_name' => 'shop',
            'db_username' => 'root',
            'db_password' => '',
        ]);

        $response->assertRedirect(route('installer.import.package'));
    }

    // --- step 3: the packages waiting ---

    #[Test]
    public function the_package_step_needs_the_database_step_first(): void
    {
        $nonce = $this->startBranch();

        $this->asBrowser($nonce)->get('/install/import/package')->assertRedirect(route('installer.database'));
    }

    #[Test]
    public function the_package_step_lists_finished_files_and_greys_out_one_still_uploading(): void
    {
        $nonce = $this->startBranch();
        $this->dropPackage('shop.oeparts', 600);
        $this->dropPackage('half.oeparts', 2);

        $response = $this->asBrowser($nonce)->withSession(['installer.db_configured' => true])->get('/install/import/package');

        $response->assertOk();
        $response->assertSee('shop.oeparts');
        $response->assertSee('half.oeparts');
        $response->assertSee('Still uploading. Wait until your FTP program has finished.');
        $response->assertSee('Package');
        $response->assertHeader('Cache-Control', 'no-store, private');
    }

    #[Test]
    public function the_package_step_explains_an_empty_inbox(): void
    {
        $nonce = $this->startBranch();

        $response = $this->asBrowser($nonce)->withSession(['installer.db_configured' => true])->get('/install/import/package');

        $response->assertSee('No package found yet. Upload the file, then press Check again.');
        $response->assertSee('scp ');
    }

    #[Test]
    public function the_package_step_hides_the_names_when_the_listing_is_switched_off(): void
    {
        config(['migration.installer.list_inbox' => false]);
        $nonce = $this->startBranch();
        $this->dropPackage('secret-shop.oeparts', 600);

        $response = $this->asBrowser($nonce)->withSession(['installer.db_configured' => true])->get('/install/import/package');

        $response->assertDontSee('secret-shop.oeparts');
        $response->assertSee('The list of files is switched off on this server.');
    }

    #[Test]
    public function a_changed_network_address_is_a_warning_not_a_gate(): void
    {
        $nonce = $this->startBranch('10.0.0.1');

        $response = $this->asBrowser($nonce)->withSession(['installer.db_configured' => true])
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
            ->get('/install/import/package');

        $response->assertOk();
        $response->assertSee('different network address');
    }

    #[Test]
    public function the_branch_does_not_exist_once_the_site_is_installed(): void
    {
        File::put(storage_path('installed.lock'), 'installed');

        $this->get('/install/import')->assertRedirect('/');
        $this->post('/install/import')->assertRedirect('/');
        $this->assertFalse($this->wizard()->exists());
    }
}
