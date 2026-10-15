<?php

namespace Tests\Feature\Migration;

use App\Services\Migration\Installer\ImportWizardState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DrivesInstallerImport;
use Tests\Support\DrivesInstallerRuns;
use Tests\TestCase;

/**
 * While a browser holds the import branch, the classic installer pages (which rewrite the shared .env or start a run) belong to
 * it; and a run is never created or confirmed once the database settings are not the ones the owner connected to.
 */
class InstallerLegacyRoutesGuardTest extends TestCase
{
    use DrivesInstallerImport;
    use DrivesInstallerRuns;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareInstallerRunTest();
    }

    protected function tearDown(): void
    {
        $this->cleanUpInstallerRunTest();

        parent::tearDown();
    }

    /** @return list<array{string, string}> */
    public static function legacyPosts(): array
    {
        return [['/install/database'], ['/install/site-settings'], ['/install/admin-account'], ['/install/email-setup'], ['/install/email-setup/test-mail']];
    }

    #[Test]
    public function a_stranger_cannot_use_the_classic_pages_while_the_owner_holds_the_branch(): void
    {
        $this->startBranch();
        $envBefore = is_file(base_path('.env')) ? md5_file(base_path('.env')) : null;
        $this->aStranger();

        foreach (self::legacyPosts() as [$uri]) {
            $this->post($uri, ['db_host' => 'evil', 'db_port' => 3306, 'db_name' => 'x', 'db_username' => 'x'])->assertStatus(403);
        }
        $this->get('/install/database')->assertStatus(403);
        $this->post('/install/run/advance')->assertStatus(403);
        $this->post('/install/retry')->assertStatus(403);
        $this->assertSame($envBefore, is_file(base_path('.env')) ? md5_file(base_path('.env')) : null, 'the .env was not touched');
    }

    #[Test]
    public function the_owner_keeps_the_database_page_but_not_the_classic_run_pages(): void
    {
        $nonce = $this->startBranch();
        $this->asBrowser($nonce);

        $this->get('/install/database')->assertOk();
        $this->get('/install/run')->assertStatus(403);
        $this->post('/install/run/advance')->assertStatus(403);
        $this->post('/install/retry')->assertStatus(403);
    }

    #[Test]
    public function with_no_branch_held_the_classic_pages_answer_as_before(): void
    {
        $this->get('/install/database')->assertOk();
        $this->assertFalse($this->wizard()->held());
    }

    #[Test]
    public function an_idle_branch_no_longer_blocks_the_classic_pages(): void
    {
        $this->startBranch();
        $this->travel(2 * 3600)->seconds();

        $this->aStranger()->get('/install/database')->assertOk();
    }

    #[Test]
    public function a_changed_database_refuses_the_run_and_the_confirmation(): void
    {
        $this->buildRunnablePackage('shop.oeparts', ['core', 'catalog', 'catalog_files', 'media']);
        $this->openRunnablePackage();
        $this->wizard()->rememberDatabase();
        $this->wizard()->change(['db_fingerprint' => hash('sha256', 'another|host')]);
        $this->post('/install/import/choose', ['selection' => 'preset:catalog_only', 'launch' => 'live', 'site_url' => 'http://shop.example'])->assertRedirect(route('installer.import.check'));

        $this->post('/install/import/check')
            ->assertRedirect(route('installer.import.package'))
            ->assertSessionHas('import_error', fn (array $e) => str_contains($e['message'], 'database settings changed'));
        $this->assertSame([], $this->runIds(), 'no run was created');
    }

    #[Test]
    public function a_matching_fingerprint_lets_the_run_start_and_a_change_afterwards_refuses_the_confirmation(): void
    {
        $this->buildRunnablePackage('shop.oeparts', ['core', 'catalog', 'catalog_files', 'media']);
        $this->openRunnablePackage();
        $this->wizard()->rememberDatabase();
        $run = $this->startRunFromInstaller(['selection' => 'preset:catalog_only']);
        $answer = $this->advanceToConfirmation($run['id'], $run['token']);

        $this->wizard()->change(['db_fingerprint' => hash('sha256', 'another|host')]);

        $this->postJson("/migrate/run/{$run['id']}/confirm", ['acks' => $this->allAcks($answer)], $this->tokenHeader($run['token']))
            ->assertStatus(422)
            ->assertJsonPath('error', 'database_changed');
        $this->assertTrue(ImportWizardState::databaseFingerprint() !== '');
    }
}
