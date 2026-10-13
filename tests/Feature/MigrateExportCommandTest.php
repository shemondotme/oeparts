<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Models\Product;
use App\Models\Setting;
use App\Security\PackageAudit;
use App\Services\Backup\BackupLock;
use App\Services\Migration\Export\ExportManager;
use App\Services\Migration\Export\ExportOptions;
use App\Services\Migration\Export\ExportPlanner;
use App\Services\Migration\Export\FreezeState;
use App\Services\Migration\Export\PackageOutbox;
use App\Services\Migration\Export\Stages\ExportFilesStage;
use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\KeyEscrow;
use App\Services\Migration\Package\PackageReader;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * Phase 3.11b: `oeparts:migrate:export` and `oeparts:migrate:unfreeze` as an operator uses them.
 */
class MigrateExportCommandTest extends TestCase
{
    use RefreshDatabase;

    private const PASSPHRASE = 'MQ4X-9T2B-7HDW-K5RN-C8VE-3YGA';

    private string $base = '';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('exportstage');
        config(['backup.staging_disk' => 'exportstage', 'migration.export.page_rows' => 50]);

        $this->base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-exportcmd-'.getmypid().'-'.uniqid();
        mkdir($this->base.'/public/media', 0775, true);
        mkdir($this->base.'/exports', 0775, true);

        $this->app->instance(PackageOutbox::class, new PackageOutbox($this->base.'/exports'));
        $this->app->instance(PackageAudit::class, new PackageAudit($this->base.'/history.ndjson'));
        $this->app->when(ExportFilesStage::class)->needs('$disks')->give(fn (): array => [
            'public' => ['path' => $this->base.'/public', 'prefix' => 'storage/app/public'],
            'local' => ['path' => $this->base.'/local', 'prefix' => 'storage/app/private'],
        ]);

        putenv('OEPARTS_PASSPHRASE');
        putenv('OE_MIGRATE_PASSPHRASE');
        $this->clean();

        Product::factory()->count(3)->create();
        file_put_contents($this->base.'/public/media/photo.jpg', random_bytes(2000));
        Setting::create(['group' => 'maintenance', 'key' => 'enabled', 'value' => '0', 'type' => 'boolean', 'is_encrypted' => false]);
    }

    protected function tearDown(): void
    {
        putenv('OEPARTS_PASSPHRASE');
        $this->clean();
        BackupRun::query()->exports()->get()->each(fn ($run) => app(KeyEscrow::class)->delete('export-'.$run->id));
        exec('rm -rf '.escapeshellarg($this->base));

        parent::tearDown();
    }

    private function clean(): void
    {
        app(BackupLock::class)->release();
        $path = FreezeState::file()->path();
        foreach ([$path, $path.'.prev', $path.'.lock'] as $file) {
            @unlink($file);
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{0: int, 1: string}
     */
    private function export(array $options = []): array
    {
        $buffer = new BufferedOutput;
        $exit = Artisan::call('oeparts:migrate:export', $options + ['--no-interaction' => true], $buffer);

        return [$exit, $buffer->fetch()];
    }

    /** @return array<int, array<string, mixed>> */
    private function events(string $output): array
    {
        return array_map(function (string $line): array {
            $decoded = json_decode($line, true);
            $this->assertIsArray($decoded, "not JSON: {$line}");

            return $decoded;
        }, array_values(array_filter(explode("\n", $output), fn (string $l): bool => trim($l) !== '')));
    }

    // ---- what to export --------------------------------------------------------------------------------

    #[Test]
    public function without_saying_what_to_export_it_asks_and_exits_two(): void
    {
        [$exit, $out] = $this->export();

        $this->assertSame(2, $exit);
        $this->assertStringContainsString('--preset=', $out);
        $this->assertStringContainsString('--groups=', $out);
        $this->assertSame(0, BackupRun::query()->exports()->count());
    }

    #[Test]
    public function a_preset_and_groups_together_a_wrong_name_and_system_data_are_all_usage_errors(): void
    {
        foreach ([
            ['--preset' => 'full_live', '--groups' => 'catalog'],
            ['--preset' => 'everything'],
            ['--groups' => 'catalog,nonsense'],
            ['--groups' => 'system'],
            ['--preset' => 'full_live', '--hold-minutes' => '-5', '--freeze' => true],
        ] as $options) {
            [$exit, $out] = $this->export($options + ['--plan-only' => true]);

            $this->assertSame(2, $exit, json_encode($options).' => '.$out);
        }
    }

    #[Test]
    public function plan_only_shows_the_plan_as_events_and_changes_nothing(): void
    {
        [$exit, $out] = $this->export(['--preset' => 'catalog_only', '--plan-only' => true, '--json' => true]);

        $events = $this->events($out);

        $this->assertSame(0, $exit);
        $this->assertSame(['plan', 'result'], array_column($events, 'event'));
        $this->assertContains('catalog', $events[0]['groups']);
        $this->assertTrue($events[1]['ok']);
        $this->assertSame(0, BackupRun::query()->exports()->count());
        $this->assertFalse(app(BackupLock::class)->isLocked());
    }

    // ---- the passphrase ---------------------------------------------------------------------------------

    #[Test]
    public function no_passphrase_source_in_a_non_interactive_run_is_a_usage_error(): void
    {
        [$exit, $out] = $this->export(['--preset' => 'catalog_only']);

        $this->assertSame(2, $exit);
        $this->assertStringContainsString('OEPARTS_PASSPHRASE', $out);
        $this->assertSame(0, BackupRun::query()->exports()->count());
    }

    #[Test]
    public function a_weak_passphrase_is_refused_in_plain_words_and_nothing_is_created(): void
    {
        putenv('OEPARTS_PASSPHRASE=short');

        [$exit, $out] = $this->export(['--preset' => 'catalog_only']);

        $this->assertSame(2, $exit);
        $this->assertStringContainsString('What to do:', $out);
        $this->assertSame(0, BackupRun::query()->exports()->count());
        $this->assertFalse(app(BackupLock::class)->isLocked());
    }

    // ---- a real export -----------------------------------------------------------------------------------

    #[Test]
    public function a_live_export_with_json_output_prints_only_json_and_leaves_a_package_that_opens(): void
    {
        putenv('OEPARTS_PASSPHRASE='.self::PASSPHRASE);

        [$exit, $out] = $this->export(['--groups' => 'catalog,media', '--json' => true]);

        $events = $this->events($out);
        $names = array_column($events, 'event');
        $result = end($events);

        $this->assertSame(0, $exit, $out);
        $this->assertContains('plan', $names);
        $this->assertContains('started', $names);
        $this->assertContains('progress', $names);
        $this->assertSame('result', end($names));
        $this->assertTrue($result['ok']);
        $this->assertSame('eventual', $result['consistency']);
        $this->assertStringNotContainsString(self::PASSPHRASE, $out);

        $reader = PackageReader::open($result['package']['path'], self::PASSPHRASE);
        $reader->verifyAll();
        $this->assertSame($result['package']['fingerprint'], $reader->fingerprint());
        $this->assertContains('db/products.0000', array_column($reader->entries(), 'name'));
    }

    #[Test]
    public function a_generated_passphrase_is_shown_once_and_opens_the_package(): void
    {
        [$exit, $out] = $this->export(['--preset' => 'catalog_only', '--generate-passphrase' => true]);

        $this->assertSame(0, $exit, $out);
        $this->assertSame(1, preg_match('/Passphrase for this package: ((?:[0-9A-Z]{4}-){5}[0-9A-Z]{4})/', $out, $match), 'it is shown');
        $this->assertStringContainsString('Write it down now', $out);

        $package = collect(app(PackageOutbox::class)->list())->first();
        PackageReader::open($package['path'], $match[1])->verifyAll();
    }

    #[Test]
    public function the_human_summary_ends_with_the_fingerprint_to_compare_and_the_settings_to_enter_again(): void
    {
        putenv('OEPARTS_PASSPHRASE='.self::PASSPHRASE);
        Setting::create(['group' => 'email', 'key' => 'smtp_password', 'value' => Crypt::encryptString('x'), 'type' => 'encrypted', 'is_encrypted' => true]);

        [$exit, $out] = $this->export(['--preset' => 'full_live']);

        $this->assertSame(0, $exit, $out);
        $this->assertMatchesRegularExpression('/Fingerprint: (?:[0-9A-Z]{4}-){3}[0-9A-Z]{4}/', $out);
        $this->assertStringContainsString('Settings you must enter again on the new site: email.smtp_password', $out);
        $this->assertStringContainsString('Consistency: eventual', $out);
    }

    // ---- freeze and unfreeze --------------------------------------------------------------------------------

    #[Test]
    public function a_freeze_export_holds_the_shop_until_unfreeze_reopens_it(): void
    {
        putenv('OEPARTS_PASSPHRASE='.self::PASSPHRASE);

        [$exit, $out] = $this->export(['--preset' => 'catalog_only', '--freeze' => true]);

        $this->assertSame(0, $exit, $out);
        $this->assertStringContainsString('The shop is still frozen', $out);
        $this->assertStringContainsString('oeparts:migrate:unfreeze', $out);
        $this->assertStringContainsString('Consistency: consistent', $out);
        $this->assertTrue(FreezeState::read()->isLive());

        $buffer = new BufferedOutput;
        $this->assertSame(0, Artisan::call('oeparts:migrate:unfreeze', [], $buffer));
        $this->assertStringContainsString('The shop is open again.', $buffer->fetch());
        $this->assertNull(FreezeState::read());

        app(SettingsService::class)->forget('maintenance');
        $this->assertFalse((bool) app(SettingsService::class)->get('maintenance.enabled', false));

        $buffer = new BufferedOutput;
        $this->assertSame(0, Artisan::call('oeparts:migrate:unfreeze', [], $buffer));
        $this->assertStringContainsString('The shop is not frozen.', $buffer->fetch());
    }

    #[Test]
    public function no_hold_reopens_the_shop_as_soon_as_the_package_is_verified(): void
    {
        putenv('OEPARTS_PASSPHRASE='.self::PASSPHRASE);

        [$exit] = $this->export(['--preset' => 'catalog_only', '--freeze' => true, '--no-hold' => true]);

        $this->assertSame(0, $exit);
        $this->assertNull(FreezeState::read());
    }

    #[Test]
    public function unfreezing_a_shop_whose_export_is_still_running_stops_that_export(): void
    {
        $manager = app(ExportManager::class);
        $plan = app(ExportPlanner::class)->plan([GroupKey::Catalog], new ExportOptions(consistency: 'freeze'));
        $run = $manager->startExport($plan, self::PASSPHRASE, 'cli');
        $manager->advance($run); // the freeze engages

        $this->assertTrue(FreezeState::read()->isLive());

        $buffer = new BufferedOutput;
        $this->assertSame(0, Artisan::call('oeparts:migrate:unfreeze', ['--json' => true], $buffer));

        $run->refresh();
        $this->assertSame(BackupRun::STATUS_FAILED, $run->status);
        $this->assertStringContainsString('Unfrozen by the operator', (string) $run->error);
        $this->assertNull(FreezeState::read());
        $this->assertNull(app(KeyEscrow::class)->get('export-'.$run->id));
        $this->assertFalse(app(BackupLock::class)->isLocked());
    }

    // ---- busy ------------------------------------------------------------------------------------------------

    #[Test]
    public function while_a_backup_holds_the_lock_the_export_says_the_system_is_busy(): void
    {
        putenv('OEPARTS_PASSPHRASE='.self::PASSPHRASE);
        $lock = app(BackupLock::class);
        file_put_contents($lock->path(), json_encode(['owner' => 'backup:5', 'acquired_at' => now()->toIso8601String()]));

        [$exit, $out] = $this->export(['--preset' => 'catalog_only']);

        $this->assertSame(6, $exit);
        $this->assertStringContainsString('What to do:', $out);
        $this->assertSame('backup:5', $lock->owner()['owner']);
        $this->assertSame(0, BackupRun::query()->exports()->count());
    }
}
