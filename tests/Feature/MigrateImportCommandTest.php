<?php

namespace Tests\Feature;

use App\Services\Backup\BackupLock;
use App\Services\Migration\ExitCode;
use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Import\ImportInbox;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\RecoveryNote;
use App\Services\Migration\Transport\HostResolver;
use App\Services\Migration\Transport\IpClassifier;
use App\Services\Migration\Transport\SafeHttpFetcher;
use App\Services\Migration\Transport\TransferManager;
use App\Services\Migration\Transport\TransferStore;
use App\Services\Migration\Transport\UrlPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Support\DrivesRealImports;
use Tests\Support\FakeHostResolver;
use Tests\Support\FakeRangeServer;
use Tests\TestCase;

/**
 * Phase 8.1: the command-line door of the import. What it refuses to guess, the exit codes, and (on a real package made by the real
 * export) the whole way through: checks, confirmation, import, budget and resume, and taking a completed import back.
 */
class MigrateImportCommandTest extends TestCase
{
    use DrivesRealImports;
    use RefreshDatabase;

    private string $passphraseFile = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareRealImport();
        $this->passphraseFile = $this->workDir.'/passphrase.txt';
        file_put_contents($this->passphraseFile, $this->passphrase);
        chmod($this->passphraseFile, 0600);
    }

    protected function tearDown(): void
    {
        RecoveryNote::remove();
        $this->cleanUpRealImport();

        parent::tearDown();
    }

    // ---- fixtures ---------------------------------------------------------------------------------------------

    /**
     * Run the command the way a script would: never interactive, the passphrase from a file.
     *
     * @param  array<string, mixed>  $args
     * @return array{0: int, 1: string}
     */
    private function cli(array $args): array
    {
        $buffer = new BufferedOutput;
        $exit = Artisan::call('oeparts:migrate:import', $args + ['--no-interaction' => true, '--passphrase-file' => $this->passphraseFile], $buffer);

        return [$exit, $buffer->fetch()];
    }

    /** The cheap, hand-built package: enough for the checks, not for a real load. */
    private function cheapPackage(array $meta = []): string
    {
        return $this->buildImportPackage('cheap-'.uniqid().'.oeparts', $meta);
    }

    private function noRunLeftOver(): void
    {
        $this->assertSame([], array_values(array_filter($this->store()->all(), fn ($s): bool => ! in_array($s->id, $this->runsBefore, true))), 'the run was discarded: nothing is left of it');
        $this->assertFalse((new ImportMarker)->isActive());
        $this->assertFalse(app(BackupLock::class)->isLocked());
    }

    // ---- what it refuses to guess ------------------------------------------------------------------------------

    #[Test]
    public function a_passphrase_on_the_command_line_is_refused(): void
    {
        [$exit, $text] = $this->cli(['source' => $this->cheapPackage(), '--launch' => 'live', '--preset' => 'full_live', '--passphrase' => 'hunter2']);

        $this->assertSame(ExitCode::Usage->value, $exit);
        $this->assertStringContainsString('--passphrase=VALUE is not accepted', $text);
    }

    #[Test]
    public function the_url_and_sha256_options_come_with_the_transfer_layer(): void
    {
        $definition = Artisan::all()['oeparts:migrate:import']->getDefinition();

        $this->assertTrue($definition->hasOption('url'));
        $this->assertTrue($definition->hasOption('sha256'));
        $this->assertTrue($definition->hasOption('resume'));
        $this->assertTrue($definition->hasOption('rollback'));
    }

    #[Test]
    public function without_a_launch_mode_or_a_choice_of_groups_a_script_is_told_what_is_missing(): void
    {
        [$exit, $text] = $this->cli(['source' => $this->cheapPackage()]);

        $this->assertSame(ExitCode::Usage->value, $exit);
        $this->assertStringContainsString('--launch=live or --launch=staging', $text);
        $this->assertStringContainsString('--preset=... or --groups=...', $text);
    }

    #[Test]
    public function with_no_package_at_all_it_says_so(): void
    {
        [$exit, $text] = $this->cli(['--launch' => 'live', '--preset' => 'full_live']);

        $this->assertSame(ExitCode::Usage->value, $exit);
        $this->assertStringContainsString('the package', $text);
    }

    #[Test]
    public function a_missing_file_and_a_wrong_passphrase_have_their_own_exit_codes_and_change_nothing(): void
    {
        [$exit] = $this->cli(['source' => $this->workDir.'/nothing-here.oeparts', '--launch' => 'live', '--preset' => 'full_live']);
        $this->assertSame(ExitCode::PackageUnreadable->value, $exit);

        file_put_contents($this->passphraseFile, 'WRONG-0000-1111-2222-3333-4444');
        [$exit, $text] = $this->cli(['source' => $this->cheapPackage(), '--launch' => 'live', '--preset' => 'full_live']);

        $this->assertSame(ExitCode::WrongPassphrase->value, $exit);
        $this->assertStringContainsString('does not open', $text);
        $this->noRunLeftOver();
    }

    // ---- the checks ---------------------------------------------------------------------------------------------

    #[Test]
    public function preflight_only_shows_the_checks_changes_nothing_and_discards_the_run(): void
    {
        $before = $this->picture();

        [$exit, $text] = $this->cli(['source' => $this->cheapPackage(), '--preflight-only' => true]);

        $this->assertSame(ExitCode::Ok->value, $exit, $text);
        $this->assertStringContainsString('The checks passed. Nothing was changed.', $text);
        $this->assertSame($before, $this->picture());
        $this->noRunLeftOver();
    }

    #[Test]
    public function a_package_from_another_release_is_blocked_with_exit_three(): void
    {
        $package = $this->cheapPackage(['migrations' => array_slice($this->ownMigrations(), 0, -3)]);

        [$exit, $text] = $this->cli(['source' => $package, '--launch' => 'live', '--preset' => 'full_live', '--confirm' => 'REPLACE']);

        $this->assertSame(ExitCode::PreflightBlocked->value, $exit);
        $this->assertStringContainsString('FAIL', $text);
        $this->noRunLeftOver();
    }

    #[Test]
    public function without_the_typed_confirmation_nothing_is_replaced(): void
    {
        $before = $this->picture();

        [$exit, $text] = $this->cli(['source' => $this->cheapPackage(), '--launch' => 'live', '--preset' => 'full_live']);

        $this->assertSame(ExitCode::NotConfirmed->value, $exit);
        $this->assertStringContainsString('--confirm=REPLACE', $text);
        $this->assertSame($before, $this->picture());
        $this->noRunLeftOver();
    }

    #[Test]
    public function a_site_that_has_been_used_needs_its_own_extra_confirmation(): void
    {
        $this->plantRow('orders', ['order_number' => 'LIVE-1']);
        $before = $this->picture();

        [$exit, $text] = $this->cli(['source' => $this->cheapPackage(), '--launch' => 'live', '--preset' => 'full_live', '--confirm' => 'REPLACE', '--accept-warnings' => true]);

        $this->assertSame(ExitCode::NotConfirmed->value, $exit, 'a script written for fresh installs can never wipe a live shop by accident');
        $this->assertStringContainsString('--replace-populated', $text);
        $this->assertSame($before, $this->picture());
        $this->noRunLeftOver();
    }

    #[Test]
    public function warnings_that_need_an_acknowledgement_have_to_be_accepted_on_purpose(): void
    {
        $this->plantRow('orders', ['order_number' => 'LIVE-1']);

        [$exit, $text] = $this->cli(['source' => $this->cheapPackage(), '--launch' => 'live', '--preset' => 'full_live', '--confirm' => 'REPLACE', '--replace-populated' => true]);

        $this->assertSame(ExitCode::NotConfirmed->value, $exit);
        $this->assertStringContainsString('--accept-warnings', $text);
        $this->noRunLeftOver();
    }

    #[Test]
    public function a_group_the_package_does_not_hold_is_refused_as_a_usage_mistake(): void
    {
        [$exit, $text] = $this->cli(['source' => $this->cheapPackage(), '--launch' => 'live', '--groups' => 'sales', '--confirm' => 'REPLACE']);

        $this->assertSame(ExitCode::Usage->value, $exit);
        $this->assertStringContainsString('does not hold', $text);
        $this->noRunLeftOver();
    }

    // ---- the whole way ----------------------------------------------------------------------------------------------

    /** @return array{0: string, 1: array<string, string>, 2: array<string, string>} the package, the picture it holds, and the picture of the used site */
    private function usedSite(): array
    {
        [$package, $source] = $this->packageOfThisSite(3, [GroupKey::Catalog, GroupKey::Content]);
        $this->plantRow('orders', ['order_number' => 'LIVE-1']);
        $this->plantRow('redirects', ['from_url' => '/only-here', 'to_url' => '/x']);

        // The tests run inside one transaction, where SQLite cannot switch foreign keys off: the live products point at parents that
        // are not there, so a parent's TRUNCATE cannot cascade into products the rollback already put back (MySQL, with the checks
        // off, never does).
        $this->deferForeignKeys();
        DB::table('products')->update(['manufacturer_id' => 9999, 'condition_id' => 9999]);
        DB::table('manufacturers')->delete();
        DB::table('conditions')->delete();

        return [$package, $source, $this->picture()];
    }

    /** @return array<string, mixed> */
    private function allFlags(string $package, array $more = []): array
    {
        return ['source' => $package, '--launch' => 'live', '--preset' => 'full_live', '--confirm' => 'REPLACE', '--replace-populated' => true, '--accept-warnings' => true] + $more;
    }

    #[Test]
    public function a_dry_run_does_everything_that_changes_nothing_and_prints_the_plan(): void
    {
        [$package, , $used] = $this->usedSite();

        [$exit, $text] = $this->cli(['source' => $package, '--dry-run' => true]);

        $this->assertSame(ExitCode::Ok->value, $exit, $text);
        $this->assertStringContainsString('The rehearsal is done. Nothing was changed.', $text);
        $this->assertSame($used, $this->picture());
        $this->assertFalse((new ImportMarker)->isActive());
    }

    #[Test]
    public function a_full_import_replaces_the_site_ends_complete_and_a_json_run_says_so_on_its_last_line(): void
    {
        [$package, $source, $used] = $this->usedSite();

        [$exit, $text] = $this->cli($this->allFlags($package, ['--json' => true]));

        $this->assertSame(ExitCode::Ok->value, $exit, $text);
        $this->assertSame($source['products'], $this->picture()['products'], 'the package\'s products are in');
        $this->assertSame('0', (string) DB::table('orders')->count(), 'the old orders are gone');
        $this->assertFalse((new ImportMarker)->isActive());
        $this->assertFileDoesNotExist(RecoveryNote::path());

        $lines = array_values(array_filter(array_map(fn (string $l): mixed => json_decode($l, true), explode("\n", trim($text))), 'is_array'));
        $this->assertNotEmpty($lines, 'standard output carries only JSON events');
        $last = end($lines);
        $this->assertSame('result', $last['event']);
        $this->assertTrue($last['ok']);
        $this->assertSame(0, $last['exit_code']);
        $this->assertNotEmpty($last['run_id']);
        $this->assertContains('progress', array_column($lines, 'event'));
        $this->assertNotSame($used, $this->picture());
    }

    #[Test]
    public function a_run_stopped_by_its_budget_exits_with_ten_and_resume_finishes_it(): void
    {
        [$package] = $this->usedSite();

        [$exit, $text] = $this->cli($this->allFlags($package, ['--budget' => 1]));

        $this->assertSame(ExitCode::NotFinished->value, $exit, $text);
        $this->assertStringContainsString('--resume', $text);

        // Resuming with the same flags carries on wherever it stopped, including a run that was still being checked.
        for ($i = 0; $i < 60 && $exit === ExitCode::NotFinished->value; $i++) {
            [$exit, $text] = $this->cli($this->allFlags($package, ['--resume' => true, '--budget' => 30]));
        }

        $this->assertSame(ExitCode::Ok->value, $exit, $text);
        $this->assertFalse((new ImportMarker)->isActive());
        $this->assertFileDoesNotExist(RecoveryNote::path());
    }

    #[Test]
    public function a_completed_import_is_taken_back_and_the_site_is_exactly_as_it_was(): void
    {
        [$package, , $used] = $this->usedSite();
        $before = file_get_contents($this->envFile);
        [$exit] = $this->cli($this->allFlags($package));
        $this->assertSame(ExitCode::Ok->value, $exit);
        $this->assertNotSame($used, $this->picture());

        [$exit, $text] = $this->cli(['--rollback' => true]);

        $this->assertSame(ExitCode::Ok->value, $exit, $text);
        $this->assertStringContainsString('exactly as it was', $text);
        $this->assertSame($used, $this->picture());
        $this->assertSame($before, file_get_contents($this->envFile));
        $this->assertFalse((new ImportMarker)->isActive());
    }

    #[Test]
    public function a_run_that_does_not_exist_cannot_be_resumed_or_taken_back(): void
    {
        [$exit] = $this->cli(['--resume' => true, '--run' => 'abcdef0123456789']);
        $this->assertSame(ExitCode::Failed->value, $exit);

        [$exit] = $this->cli(['--rollback' => true]);
        $this->assertSame(ExitCode::Failed->value, $exit, 'nothing to take back');
    }

    // ---- a package from a link (Phase 9.4) ------------------------------------------------------------------------

    /** Serve this file from a link that lives inside the container: a fake range server, a temporary inbox and transfer folder. */
    private function serveFromLink(string $file): FakeRangeServer
    {
        $server = new FakeRangeServer((string) file_get_contents($file));
        $resolver = new FakeHostResolver;
        mkdir($this->workDir.'/link-inbox', 0700, true);

        $this->app->instance(HostResolver::class, $resolver);
        $this->app->instance(TransferStore::class, new TransferStore($this->workDir.'/link-transfers'));
        $this->app->instance(ImportInbox::class, new ImportInbox($this->workDir.'/link-inbox'));
        $this->app->instance(SafeHttpFetcher::class, new SafeHttpFetcher(new UrlPolicy($resolver, new IpClassifier), $server->handler()));
        $this->app->forgetInstance(TransferManager::class);

        return $server;
    }

    #[Test]
    public function a_checksum_without_a_link_and_a_source_together_with_a_link_are_usage_errors(): void
    {
        [$exit, $text] = $this->cli(['source' => $this->cheapPackage(), '--sha256' => str_repeat('a', 64), '--preflight-only' => true]);
        $this->assertSame(ExitCode::Usage->value, $exit);
        $this->assertStringContainsString('--sha256 goes with --url', $text);

        [$exit, $text] = $this->cli(['source' => $this->cheapPackage(), '--url' => 'https://files.example.com/p.oeparts', '--preflight-only' => true]);
        $this->assertSame(ExitCode::Usage->value, $exit);
        $this->assertStringContainsString('not both', $text);
    }

    #[Test]
    public function a_package_from_a_link_is_downloaded_into_the_inbox_and_then_checked(): void
    {
        $package = $this->cheapPackage();
        $this->serveFromLink($package);
        $before = $this->picture();

        [$exit, $text] = $this->cli(['--url' => 'https://files.example.com/pkg/site.oeparts', '--sha256' => hash_file('sha256', $package), '--preflight-only' => true]);

        $this->assertSame(ExitCode::Ok->value, $exit, $text);
        $this->assertStringContainsString('Downloading the package from files.example.com', $text);
        $this->assertStringContainsString('The checks passed. Nothing was changed.', $text);
        $this->assertFileEquals($package, $this->workDir.'/link-inbox/site.oeparts');
        $this->assertSame($before, $this->picture());
        $this->noRunLeftOver();
    }

    #[Test]
    public function a_package_that_does_not_match_its_link_checksum_never_starts_an_import(): void
    {
        $package = $this->cheapPackage();
        $this->serveFromLink($package);

        [$exit, $text] = $this->cli(['--url' => 'https://files.example.com/pkg/site.oeparts', '--sha256' => str_repeat('0', 64), '--launch' => 'live', '--preset' => 'full_live', '--confirm' => 'REPLACE']);

        $this->assertSame(ExitCode::PackageUnreadable->value, $exit, $text);
        $this->assertStringContainsString('File changed on the way', $text);
        $this->assertSame([], glob($this->workDir.'/link-inbox/*'));
        $this->noRunLeftOver();
    }
}
