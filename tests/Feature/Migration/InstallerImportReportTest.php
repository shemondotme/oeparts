<?php

namespace Tests\Feature\Migration;

use App\Support\AtomicJsonFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DrivesInstallerRuns;
use Tests\TestCase;

/**
 * The report page the runner hands out when an installer import has ended: a signed address that works for two hours, and a page
 * that shows counts, the NAMES of settings and plain sentences, never a value.
 *
 * The reports here are written straight into a temporary run folder, with extra fields that no real report holds (a canary secret
 * at several levels), to prove that the page copies by name and cannot leak what it was not meant to show.
 */
class InstallerImportReportTest extends TestCase
{
    use DrivesInstallerRuns;
    use RefreshDatabase;

    private const CANARIES = ['CANARY-SECRET-VALUE', 'CANARY-PASSWORD', 'CANARY-ADDRESS@secret.example', 'CANARY-KEY-MATERIAL'];

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

    /**
     * A finished run's report in the temporary store.
     *
     * @param  array<string, mixed>  $overrides  merged over a complete report of an installer import
     */
    private function report(array $overrides = [], string $door = 'installer'): string
    {
        $id = bin2hex(random_bytes(8));
        mkdir($this->runStore()->directory($id), 0700, true);

        $report = array_replace_recursive([
            'report' => 1,
            'values_included' => false,
            'run' => ['id' => $id, 'kind' => 'import', 'status' => 'complete', 'door' => $door, 'launch' => 'live', 'seconds' => 125, 'changed_the_site' => true, 'initiated_by' => 'CANARY-ADDRESS@secret.example'],
            'package' => ['file' => 'CANARY-SECRET-VALUE.oeparts', 'fingerprint' => 'CANARY-KEY-MATERIAL', 'groups' => ['core']],
            'checks' => [['key' => 'disk', 'label' => 'Disk', 'status' => 'warn', 'message' => 'CANARY-PASSWORD']],
            'database' => ['tables_loaded' => 7, 'rows_loaded' => 12345, 'rows' => ['users' => 5, 'settings' => 'CANARY-SECRET-VALUE']],
            'files' => ['written' => 40, 'unchanged' => 2, 'bytes' => 1024, 'rejected' => ['php_file' => 3], 'leftovers' => 1],
            'finishing' => [
                'settings_to_enter_again' => ['mail.password', 'payments.api_key'],
                'adjusted_for_you' => ['general.site_url' => 'set to the new address', 'maintenance.enabled' => 'switched off (the package was taken while the shop was in maintenance)'],
                'switched_off' => ['backup.schedule_enabled'],
                'secret_dump' => ['mail.password' => 'CANARY-PASSWORD', 'payments.api_key' => 'CANARY-KEY-MATERIAL'],
            ],
            'warnings' => ['Disk: a lot of room is needed', 'Enter the setting mail.password again: it could not be carried over.'],
            'outcome' => ['status' => 'complete', 'error_code' => null, 'error_stage' => null],
            'secrets' => ['value' => 'CANARY-SECRET-VALUE'],
            'passphrase' => 'CANARY-PASSWORD',
        ], $overrides);

        (new AtomicJsonFile($this->runStore()->directory($id).DIRECTORY_SEPARATOR.'report.json'))->write($report);

        return $id;
    }

    private function signed(string $id, ?\DateTimeInterface $until = null): string
    {
        return URL::temporarySignedRoute('installer.import.report', $until ?? now()->addHours(2), ['id' => $id]);
    }

    // --- what the page says ---

    #[Test]
    public function a_finished_import_shows_counts_names_and_what_to_do_next(): void
    {
        $id = $this->report();

        $page = $this->get($this->signed($id));

        $page->assertOk();
        $page->assertSee('Your site has been imported');
        $page->assertSee('2 things need your attention');
        $page->assertSee('Use the admin email and password from your OLD site.');
        $page->assertSee('12,345 rows in 7 tables');
        $page->assertSee('40 copied, 2 already there');
        $page->assertSee('3 refused for safety');
        $page->assertSee('your live site');
        $page->assertSee('Do this before going live');
        $page->assertSee('Good to do');
        $page->assertSee('Enter these settings again');
        $page->assertSee('mail.password');
        $page->assertSee('payments.api_key');
        $page->assertSee('What we adjusted for you');
        $page->assertSee('general.site_url');
        $page->assertSee('set to the new address');
        $page->assertSee('backup.schedule_enabled');
        $page->assertSee('Things to check');
        $page->assertSee('Disk: a lot of room is needed');
        $page->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));
    }

    #[Test]
    public function the_page_copies_by_name_so_no_value_can_reach_it(): void
    {
        $id = $this->report();

        $content = (string) $this->get($this->signed($id))->assertOk()->getContent();

        foreach (self::CANARIES as $canary) {
            $this->assertStringNotContainsString($canary, $content, "{$canary} is in the report file but must not be on the page");
        }
        $this->assertStringNotContainsString('secret.example', $content);
        $this->assertStringNotContainsString($this->tmp, $content, 'no path');
    }

    #[Test]
    public function a_run_that_changed_nothing_says_so_and_a_rolled_back_run_says_it_was_put_back(): void
    {
        $failed = $this->report(['run' => ['status' => 'failed', 'changed_the_site' => false], 'outcome' => ['status' => 'failed', 'error_code' => 'package_changed']]);
        $this->get($this->signed($failed))->assertOk()->assertSee('The import did not finish')->assertSee('before anything was changed')->assertDontSee('Do this before going live');

        $back = $this->report(['run' => ['status' => 'rolled_back'], 'outcome' => ['status' => 'rolled_back']]);
        $this->get($this->signed($back))->assertOk()->assertSee('put back as it was')->assertDontSee('Do this before going live');

        $stuck = $this->report(['run' => ['status' => 'failed', 'changed_the_site' => true], 'outcome' => ['status' => 'failed']]);
        $this->get($this->signed($stuck))->assertOk()->assertSee('oeparts:migrate:import --resume');
    }

    // --- who may read it ---

    #[Test]
    public function an_address_without_a_valid_signature_is_refused(): void
    {
        $id = $this->report();

        $this->get("/install/import/report/{$id}")->assertForbidden();

        $signed = $this->signed($id);
        $this->get(substr($signed, 0, -3).'abc')->assertForbidden();
        $this->get(str_replace('expires=', 'expires=9', $signed))->assertForbidden();
    }

    #[Test]
    public function the_address_stops_working_after_two_hours(): void
    {
        $id = $this->report();
        $signed = $this->signed($id);

        $this->get($signed)->assertOk();

        $this->travel(121)->minutes();

        $this->get($signed)->assertForbidden();
    }

    #[Test]
    public function a_signature_made_for_one_run_does_not_open_another(): void
    {
        $mine = $this->report();
        $other = $this->report();
        $signed = $this->signed($mine);

        $this->get(str_replace($mine, $other, $signed))->assertForbidden();
    }

    #[Test]
    public function only_the_report_of_an_installer_run_is_served_here(): void
    {
        $this->get($this->signed($this->report([], 'admin')))->assertNotFound();
        $this->get($this->signed($this->report([], 'cli')))->assertNotFound();
    }

    #[Test]
    public function an_unknown_run_or_a_damaged_report_is_a_plain_404(): void
    {
        $this->get($this->signed('0123456789abcdef'))->assertNotFound();

        $id = bin2hex(random_bytes(8));
        mkdir($this->runStore()->directory($id), 0700, true);
        file_put_contents($this->runStore()->directory($id).DIRECTORY_SEPARATOR.'report.json', '{ this is not json');
        $this->get($this->signed($id))->assertNotFound();

        $this->get('/install/import/report/NOT_AN_ID')->assertNotFound();
    }

    #[Test]
    public function the_route_sits_outside_the_installer_group_and_asks_for_a_signature(): void
    {
        $route = app('router')->getRoutes()->getByName('installer.import.report');

        $this->assertNotNull($route);
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $middleware = $route->gatherMiddleware();
        $this->assertContains('signed', $middleware);
        $this->assertNotContains('installer', $middleware, 'the lock exists by the time anyone reads it');
        $this->assertNotContains('import.wizard', $middleware, 'another device may open the link');
    }
}
