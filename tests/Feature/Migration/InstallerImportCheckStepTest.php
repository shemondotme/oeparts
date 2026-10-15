<?php

namespace Tests\Feature\Migration;

use App\Services\Migration\Import\ImportManager;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DrivesInstallerRuns;
use Tests\TestCase;

/**
 * Step 5 of the installer's import branch: a page that shows what was chosen (and creates nothing), and the one POST that turns
 * the branch into a run and hands the browser to the runner page.
 *
 * Real packages, a real unlock and the real engine's first step (taking the installer's keys over); every folder, the run state
 * included, is temporary.
 */
class InstallerImportCheckStepTest extends TestCase
{
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

    private const CATALOG_PRESET = ['selection' => 'preset:catalog_only'];

    // --- the page ---

    #[Test]
    public function the_installers_first_pages_do_not_build_the_import_engine(): void
    {
        // The engine reaches into the database and the application's services; the installer's first pages run before there is a database.
        $this->get('/install/import')->assertOk();
        $this->assertFalse(app()->resolved(ImportManager::class));
    }

    #[Test]
    public function the_page_shows_what_was_chosen_and_a_get_creates_nothing(): void
    {
        $this->buildRunnablePackage();
        $this->openRunnablePackage();
        $this->post('/install/import/choose', self::CATALOG_PRESET + ['launch' => 'staging', 'site_url' => 'http://test.shop.example'])
            ->assertRedirect(route('installer.import.check'));

        $page = $this->get('/install/import/check');

        $page->assertOk();
        $page->assertSee('Ready to check the package');
        $page->assertSee('shop.oeparts');
        $page->assertSee('old.example');
        $page->assertSee('Catalog only');
        $page->assertSee('Site basics, Products, Product pictures, Media library');
        $page->assertSee('A test copy');
        $page->assertSee('http://test.shop.example');
        $page->assertSee('Check the package');
        $page->assertSee('orders need customers', false);

        $this->assertSame([], $this->runIds(), 'looking at the page made no run');
        $this->assertArrayNotHasKey('run', $this->wizard()->read());
    }

    #[Test]
    public function the_page_needs_the_database_step_an_open_package_and_whole_choices(): void
    {
        $this->buildRunnablePackage();

        // The database step was not done.
        $this->asBrowser($this->startBranch());
        $this->get('/install/import/check')->assertRedirect(route('installer.database'));
        $this->post('/install/import/check')->assertRedirect(route('installer.database'));

        // No package is open.
        $this->withSession(['installer.db_configured' => true]);
        $this->get('/install/import/check')->assertRedirect(route('installer.import.package'));
        $this->post('/install/import/check')->assertRedirect(route('installer.import.package'));

        // A package is open, nothing is chosen.
        $this->openPackage()->assertRedirect(route('installer.import.package'));
        $this->get('/install/import/check')->assertRedirect(route('installer.import.choose'));
        $this->post('/install/import/check')->assertRedirect(route('installer.import.choose'));

        $this->assertSame([], $this->runIds());
    }

    // --- the press ---

    #[Test]
    public function pressing_check_creates_the_run_and_hands_the_browser_to_the_runner_with_the_token_in_the_fragment(): void
    {
        $this->buildRunnablePackage();
        $this->openRunnablePackage();
        $escrowId = (string) $this->wizard()->read()['unlock']['escrow_id'];
        $this->assertNotNull($this->escrow->get($escrowId), 'the installer holds the unlocked keys');

        $run = $this->startRunFromInstaller(self::CATALOG_PRESET + ['launch' => 'live']);
        $state = $this->runStore()->find($run['id']);

        $this->assertNotNull($state);
        $this->assertSame([$run['id']], $this->runIds());
        $this->assertSame('import', $state->kind);
        $this->assertSame(hash('sha256', $run['token']), $state->tokenHash, 'only the hash of the token is kept');
        $this->assertSame('inbox', $state->package['origin']);
        $this->assertTrue($state->flag('locate.done'), 'the installer\'s keys were taken over: no passphrase was asked for');
        $this->assertFalse($state->flag('needs_passphrase'));
        $this->assertSame('verify', $state->stage);

        // What the run was told, by installer code.
        $this->assertSame('installer', $state->options['door']);
        $this->assertSame($escrowId, $state->options['adopt_escrow']);
        $this->assertSame('live', $state->options['launch']);
        $this->assertSame('http://shop.example', $state->options['url']);
        $this->assertSame('catalog_only', $state->options['requested_preset']);
        $this->assertEqualsCanonicalizing(['core', 'catalog', 'catalog_files', 'media'], $state->options['requested_groups']);
        $this->assertArrayNotHasKey('groups', $state->options, 'the final groups are decided at the confirmation, when the structure exists');

        // The run holds its own copy of the keys; the installer's entry is gone.
        $this->assertNotNull($this->escrow->get('import-'.$run['id']));
        $this->assertNull($this->escrow->get($escrowId));
        $this->assertSame($run['id'], $this->wizard()->read()['run']);

        // Nothing secret reached the state file, the wizard file or the run's address except the token in the fragment.
        $stateText = (string) file_get_contents($this->runStore()->file($run['id'])->path());
        $wizardText = (string) file_get_contents($this->wizard()->path());
        foreach ([$this->passphrase, $run['token']] as $secret) {
            $this->assertStringNotContainsString($secret, $stateText);
            $this->assertStringNotContainsString($secret, $wizardText);
        }
        $this->assertStringNotContainsString($run['token'], explode('#', $run['location'])[0], 'the token is in the fragment only');

        // The runner answers to the token.
        $this->getJson("/migrate/run/{$run['id']}/status", $this->tokenHeader($run['token']))->assertOk()->assertJsonPath('stage', 'verify');
    }

    #[Test]
    public function a_second_press_does_not_make_a_second_run(): void
    {
        $this->buildRunnablePackage();
        $this->openRunnablePackage();
        $this->startRunFromInstaller(self::CATALOG_PRESET);

        // The keys moved into the run: the branch has nothing left to start another one with.
        $this->post('/install/import/check')->assertRedirect(route('installer.import.package'))->assertSessionHas('error');

        $this->assertCount(1, $this->runIds());
    }

    #[Test]
    public function keys_that_are_gone_ask_for_the_passphrase_again_and_no_run_is_made(): void
    {
        $this->buildRunnablePackage();
        $this->openRunnablePackage();
        $this->post('/install/import/choose', self::CATALOG_PRESET + ['launch' => 'live', 'site_url' => 'http://shop.example'])->assertRedirect();
        $this->escrow->delete((string) $this->wizard()->read()['unlock']['escrow_id']);

        $this->post('/install/import/check')->assertRedirect(route('installer.import.package'));

        $this->assertSame([], $this->runIds());
    }

    #[Test]
    public function a_package_that_changed_after_it_was_opened_is_refused_and_leaves_no_run_behind(): void
    {
        $this->buildRunnablePackage('shop.oeparts');
        $this->buildRunnablePackage('other.oeparts', ['core', 'catalog'], ['source' => ['domain' => 'other.example']]);
        $this->openRunnablePackage('shop.oeparts');
        $this->post('/install/import/choose', self::CATALOG_PRESET + ['launch' => 'live', 'site_url' => 'http://shop.example'])->assertRedirect();
        $escrowId = (string) $this->wizard()->read()['unlock']['escrow_id'];

        // An FTP user swaps the file between the passphrase and the check.
        copy($this->inboxDir().DIRECTORY_SEPARATOR.'other.oeparts', $this->inboxDir().DIRECTORY_SEPARATOR.'shop.oeparts');
        touch($this->inboxDir().DIRECTORY_SEPARATOR.'shop.oeparts', time() - 600);

        $this->post('/install/import/check')
            ->assertRedirect(route('installer.import.package'))
            ->assertSessionHas('import_error', fn (array $error): bool => $error['title'] !== '' && $error['message'] !== '');

        $this->assertSame([], $this->runIds(), 'the run that could not read its package was discarded');
        $this->assertNotNull($this->escrow->get($escrowId), 'the installer\'s keys were not consumed');
    }

    #[Test]
    public function pressing_check_again_after_going_back_replaces_the_unconfirmed_run(): void
    {
        $this->buildRunnablePackage();
        $this->openRunnablePackage();
        $first = $this->startRunFromInstaller(self::CATALOG_PRESET);

        // Back to the package step: type the passphrase again, keep the choices, check again.
        $this->openPackage()->assertRedirect(route('installer.import.package'));
        $second = $this->startRunFromInstaller(self::CATALOG_PRESET);

        $this->assertNotSame($first['id'], $second['id']);
        $this->assertSame([$second['id']], $this->runIds(), 'the first run, which nobody confirmed, is gone');
        $this->getJson("/migrate/run/{$first['id']}/status", $this->tokenHeader($first['token']))->assertNotFound();
    }

    // --- who may press ---

    #[Test]
    public function another_browser_can_neither_see_the_page_nor_press_the_button(): void
    {
        $this->buildRunnablePackage();
        $this->openRunnablePackage();
        $this->post('/install/import/choose', self::CATALOG_PRESET + ['launch' => 'live', 'site_url' => 'http://shop.example'])->assertRedirect();
        $wizardBefore = (string) file_get_contents($this->wizard()->path());

        $this->aStranger();
        $this->get('/install/import/check')->assertForbidden();
        $this->post('/install/import/check')->assertForbidden();

        $this->assertSame([], $this->runIds());
        $this->assertSame($wizardBefore, (string) file_get_contents($this->wizard()->path()));
    }

    #[Test]
    public function the_press_is_a_post_with_a_csrf_token_and_the_page_is_a_plain_get(): void
    {
        $router = app('router');
        $post = $router->getRoutes()->getByName('installer.import.check.run');
        $page = $router->getRoutes()->getByName('installer.import.check');

        $this->assertNotNull($post);
        $this->assertSame(['POST'], $post->methods());
        $this->assertSame(['GET', 'HEAD'], $page->methods());
        $this->assertSame($post->uri(), $page->uri(), 'the same address: a GET only shows, the POST creates');

        $csrf = array_filter($router->gatherRouteMiddleware($post), static fn ($m): bool => is_string($m) && is_a($m, ValidateCsrfToken::class, true));
        $this->assertNotEmpty($csrf, 'the press goes through the installer\'s CSRF check');
        $this->assertContains('import.wizard', $post->gatherMiddleware(), 'and through the owner check of the wizard');
    }

    #[Test]
    public function the_import_branch_has_no_upload_route_and_every_post_is_csrf_checked(): void
    {
        $router = app('router');
        $seen = 0;

        foreach ($router->getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'install')) {
                continue;
            }

            $seen++;
            $this->assertStringNotContainsString('upload', strtolower($route->uri()), $route->uri());
            $this->assertStringNotContainsString('upload', strtolower((string) $route->getActionName()), $route->uri());

            if (in_array('POST', $route->methods(), true)) {
                $csrf = array_filter($router->gatherRouteMiddleware($route), static fn ($m): bool => is_string($m) && is_a($m, ValidateCsrfToken::class, true));
                $this->assertNotEmpty($csrf, 'POST '.$route->uri().' is CSRF checked');
            }
        }

        $this->assertGreaterThan(10, $seen);
    }
}
