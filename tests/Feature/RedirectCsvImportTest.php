<?php

namespace Tests\Feature;

use App\Enums\RedirectType;
use App\Filament\Resources\RedirectResource\Pages\ListRedirects;
use App\Jobs\ImportRedirectsFromCsv;
use App\Models\Admin;
use App\Models\Redirect;
use App\Services\RedirectLoopDetector;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * RedirectResource's CSV export (from_url/to_url/type/hit_count/is_active)
 * had no inverse — migrating a URL structure or restoring from a backup
 * meant manual re-entry through the form, one redirect at a time. The
 * import runs the exact same self-redirect/reverse-pair/full-chain loop
 * validation as the single-row and bulk "create redirect" actions.
 */
class RedirectCsvImportTest extends TestCase
{
    use RefreshDatabase;

    private function writeCsv(string $contents): string
    {
        Storage::fake('local');
        $path = 'imports/redirects/test-'.uniqid().'.csv';
        Storage::disk('local')->put($path, $contents);

        return $path;
    }

    #[Test]
    public function it_imports_valid_rows_and_reports_the_created_count(): void
    {
        $path = $this->writeCsv(
            "from_url,to_url,type,is_active\n"
            ."old-page-a,/new-page-a,301,1\n"
            ."old-page-b,/new-page-b,302,1\n"
        );

        (new ImportRedirectsFromCsv($path, 'Test Admin'))->handle(new RedirectLoopDetector);

        $this->assertDatabaseHas('redirects', ['from_url' => 'old-page-a', 'to_url' => '/new-page-a', 'type' => RedirectType::Permanent->value]);
        $this->assertDatabaseHas('redirects', ['from_url' => 'old-page-b', 'to_url' => '/new-page-b', 'type' => RedirectType::Temporary->value]);
    }

    #[Test]
    public function it_accepts_the_exact_export_column_headers(): void
    {
        // Case-insensitive match against exactly what
        // AdminUi::exportCsvBulkAction('Export Redirects', [...]) produces
        // — a round-tripped export file must import back in unmodified.
        $path = $this->writeCsv(
            "From URL,To URL,Type,Hits,Active\n"
            ."old-page,/new-page,301,42,1\n"
        );

        (new ImportRedirectsFromCsv($path, 'Test Admin'))->handle(new RedirectLoopDetector);

        $this->assertDatabaseHas('redirects', ['from_url' => 'old-page', 'to_url' => '/new-page']);
    }

    #[Test]
    public function it_skips_a_row_that_would_form_a_loop_but_still_imports_the_rest(): void
    {
        Redirect::create(['from_url' => 'shared-dest', 'to_url' => 'old-page-a', 'type' => RedirectType::Permanent, 'is_active' => true]);

        $path = $this->writeCsv(
            "from_url,to_url,type,is_active\n"
            // Reverse of the existing redirect above — would loop.
            ."old-page-a,shared-dest,301,1\n"
            ."old-page-b,/new-page-b,301,1\n"
        );

        (new ImportRedirectsFromCsv($path, 'Test Admin'))->handle(new RedirectLoopDetector);

        $this->assertDatabaseMissing('redirects', ['from_url' => 'old-page-a', 'to_url' => 'shared-dest']);
        $this->assertDatabaseHas('redirects', ['from_url' => 'old-page-b', 'to_url' => '/new-page-b']);
    }

    #[Test]
    public function it_skips_a_row_that_duplicates_an_existing_from_url(): void
    {
        Redirect::create(['from_url' => 'already-exists', 'to_url' => '/somewhere', 'type' => RedirectType::Permanent, 'is_active' => true]);

        $path = $this->writeCsv(
            "from_url,to_url,type,is_active\n"
            ."already-exists,/different-target,301,1\n"
        );

        (new ImportRedirectsFromCsv($path, 'Test Admin'))->handle(new RedirectLoopDetector);

        $this->assertSame(1, Redirect::where('from_url', 'already-exists')->count());
        $this->assertDatabaseHas('redirects', ['from_url' => 'already-exists', 'to_url' => '/somewhere']);
    }

    #[Test]
    public function it_defaults_type_to_permanent_and_is_active_to_true_when_columns_are_missing(): void
    {
        $path = $this->writeCsv(
            "from_url,to_url\n"
            ."old-page,/new-page\n"
        );

        (new ImportRedirectsFromCsv($path, 'Test Admin'))->handle(new RedirectLoopDetector);

        $this->assertDatabaseHas('redirects', [
            'from_url' => 'old-page', 'to_url' => '/new-page',
            'type' => RedirectType::Permanent->value, 'is_active' => true,
        ]);
    }

    #[Test]
    public function overwrite_existing_off_by_default_still_skips_a_duplicate_from_url(): void
    {
        Redirect::create(['from_url' => 'already-exists', 'to_url' => '/original-target', 'type' => RedirectType::Permanent, 'is_active' => true]);

        $path = $this->writeCsv("from_url,to_url\nalready-exists,/attempted-new-target\n");

        (new ImportRedirectsFromCsv($path, 'Test Admin', overwriteExisting: false))->handle(new RedirectLoopDetector);

        $this->assertDatabaseHas('redirects', ['from_url' => 'already-exists', 'to_url' => '/original-target']);
    }

    #[Test]
    public function overwrite_existing_on_updates_an_existing_redirects_destination(): void
    {
        // A backup restore or bulk URL-migration re-import both need this —
        // matches the "create vs update on a matching row" option the bulk
        // Product importer already offers.
        $redirect = Redirect::create(['from_url' => 'old-page', 'to_url' => '/stale-target', 'type' => RedirectType::Permanent, 'is_active' => true]);

        $path = $this->writeCsv("from_url,to_url,type,is_active\nold-page,/corrected-target,302,0\n");

        (new ImportRedirectsFromCsv($path, 'Test Admin', overwriteExisting: true))->handle(new RedirectLoopDetector);

        $this->assertSame(1, Redirect::where('from_url', 'old-page')->count());
        $this->assertDatabaseHas('redirects', [
            'id' => $redirect->id, 'from_url' => 'old-page', 'to_url' => '/corrected-target',
            'type' => RedirectType::Temporary->value, 'is_active' => false,
        ]);
    }

    #[Test]
    public function overwrite_existing_on_still_skips_a_row_that_would_form_a_loop(): void
    {
        Redirect::create(['from_url' => 'shared-dest', 'to_url' => 'old-page', 'type' => RedirectType::Permanent, 'is_active' => true]);
        $redirect = Redirect::create(['from_url' => 'old-page', 'to_url' => '/somewhere', 'type' => RedirectType::Permanent, 'is_active' => true]);

        // Would form a direct reverse-pair loop with the "shared-dest"
        // redirect above if applied.
        $path = $this->writeCsv("from_url,to_url\nold-page,shared-dest\n");

        (new ImportRedirectsFromCsv($path, 'Test Admin', overwriteExisting: true))->handle(new RedirectLoopDetector);

        $this->assertDatabaseHas('redirects', ['id' => $redirect->id, 'from_url' => 'old-page', 'to_url' => '/somewhere']);
    }

    #[Test]
    public function overwrite_existing_on_does_not_flag_an_unchanged_row_as_looping_into_itself(): void
    {
        $redirect = Redirect::create(['from_url' => 'old-page', 'to_url' => '/somewhere', 'type' => RedirectType::Permanent, 'is_active' => true]);

        // Re-importing the exact same row unchanged must not have the
        // existing record's own reverse-pair excluded incorrectly.
        $path = $this->writeCsv("from_url,to_url,type,is_active\nold-page,/somewhere,301,1\n");

        (new ImportRedirectsFromCsv($path, 'Test Admin', overwriteExisting: true))->handle(new RedirectLoopDetector);

        $this->assertSame(1, Redirect::where('from_url', 'old-page')->count());
        $this->assertDatabaseHas('redirects', ['id' => $redirect->id, 'from_url' => 'old-page', 'to_url' => '/somewhere']);
    }

    #[Test]
    public function it_deletes_the_uploaded_file_after_processing(): void
    {
        $path = $this->writeCsv("from_url,to_url\nold-page,/new-page\n");

        (new ImportRedirectsFromCsv($path, 'Test Admin'))->handle(new RedirectLoopDetector);

        $this->assertFalse(Storage::disk('local')->exists($path));
    }

    #[Test]
    public function the_import_action_dispatches_the_job_with_the_uploaded_file(): void
    {
        Storage::fake('local');
        Bus::fake();
        $this->seed(RolesSeeder::class);

        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        Livewire::test(ListRedirects::class)
            ->callTableAction('importCsv', data: [
                'csv_file' => UploadedFile::fake()->createWithContent('redirects.csv', "from_url,to_url\nold-page,/new-page\n"),
            ]);

        Bus::assertDispatched(ImportRedirectsFromCsv::class, fn ($job) => $job->overwriteExisting === false);
    }

    #[Test]
    public function the_import_action_passes_the_overwrite_toggle_through_to_the_job(): void
    {
        Storage::fake('local');
        Bus::fake();
        $this->seed(RolesSeeder::class);

        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        Livewire::test(ListRedirects::class)
            ->callTableAction('importCsv', data: [
                'csv_file' => UploadedFile::fake()->createWithContent('redirects.csv', "from_url,to_url\nold-page,/new-page\n"),
                'overwrite_existing' => true,
            ]);

        Bus::assertDispatched(ImportRedirectsFromCsv::class, fn ($job) => $job->overwriteExisting === true);
    }

    /**
     * Phase 19 (Import/Export Functionality). Excel's "CSV UTF-8" export —
     * a completely normal way for an admin to have edited a round-tripped
     * export before re-uploading it — always prepends a UTF-8 BOM, which
     * fgetcsv() does not strip. Confirmed live (before the fix) that this
     * silently broke matching the first column: strtolower(trim("\xEF\xBB
     * \xBFfrom_url")) !== "from_url", so a perfectly valid file produced
     * "Missing required columns" — the fix strips it from the first header
     * cell specifically.
     */
    #[Test]
    public function a_utf8_bom_prefixed_header_still_imports_correctly(): void
    {
        $path = $this->writeCsv(
            "\xEF\xBB\xBFfrom_url,to_url,type,is_active\n"
            ."old-page,/new-page,301,1\n"
        );

        (new ImportRedirectsFromCsv($path, 'Test Admin'))->handle(new RedirectLoopDetector);

        $this->assertDatabaseHas('redirects', ['from_url' => 'old-page', 'to_url' => '/new-page']);
    }

    #[Test]
    public function an_empty_file_is_reported_cleanly_not_as_a_crash(): void
    {
        $path = $this->writeCsv('');

        (new ImportRedirectsFromCsv($path, 'Test Admin'))->handle(new RedirectLoopDetector);

        $this->assertSame(0, Redirect::count());
        // No exception propagated — notifyResult() ran instead, matching
        // the "malformed input never crashes the job" contract the method
        // already documents for its other early-return paths.
    }

    #[Test]
    public function a_file_missing_both_required_columns_is_reported_cleanly_not_as_a_crash(): void
    {
        $path = $this->writeCsv("some_column,another_column\nfoo,bar\n");

        (new ImportRedirectsFromCsv($path, 'Test Admin'))->handle(new RedirectLoopDetector);

        $this->assertSame(0, Redirect::count());
    }

    /**
     * Phase 19 (Import/Export Functionality). No DB transaction wraps the
     * per-row loop (by design — a routine, re-runnable action per the
     * job's own docblock, not the Backup Engine's chunked-FSM machinery).
     * Proves that design choice is actually SAFE: a crash partway through
     * leaves earlier rows committed (no rollback), and simply re-running
     * the same file converges to the fully-correct end state — the
     * default overwriteExisting=false behavior means an already-imported
     * row is safely skipped, not duplicated, on the re-run.
     */
    #[Test]
    public function a_mid_import_crash_leaves_earlier_rows_committed_and_a_rerun_safely_completes_the_rest(): void
    {
        $csv = "from_url,to_url\n"
            ."row-one,/dest-one\n"
            ."row-two,/dest-two\n"
            ."row-three,/dest-three\n";

        $flakyDetector = new class extends RedirectLoopDetector
        {
            private int $calls = 0;

            public function findLoop(string $from, string $to, ?int $ignoreId = null): ?string
            {
                $this->calls++;
                if ($this->calls === 2) {
                    throw new \RuntimeException('simulated mid-import DB blip');
                }

                return parent::findLoop($from, $to, $ignoreId);
            }
        };

        $path = $this->writeCsv($csv);

        try {
            (new ImportRedirectsFromCsv($path, 'Test Admin'))->handle($flakyDetector);
            $this->fail('Expected the simulated blip to propagate out of handle().');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated mid-import DB blip', $e->getMessage());
        }

        $this->assertDatabaseHas('redirects', ['from_url' => 'row-one']);
        $this->assertDatabaseMissing('redirects', ['from_url' => 'row-two']);
        $this->assertDatabaseMissing('redirects', ['from_url' => 'row-three']);

        // Natural recovery: the admin re-uploads the same file.
        $rerunPath = $this->writeCsv($csv);
        (new ImportRedirectsFromCsv($rerunPath, 'Test Admin'))->handle(new RedirectLoopDetector);

        $this->assertSame(1, Redirect::where('from_url', 'row-one')->count());
        $this->assertDatabaseHas('redirects', ['from_url' => 'row-two']);
        $this->assertDatabaseHas('redirects', ['from_url' => 'row-three']);
    }

    #[Test]
    public function the_template_download_action_streams_a_valid_starting_csv(): void
    {
        // A fresh install has no prior export to reverse-engineer the
        // column shape from — this is the blank starting point instead.
        $this->seed(RolesSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        Livewire::test(ListRedirects::class)
            ->callTableAction('downloadRedirectTemplate')
            ->assertFileDownloaded('redirect-import-template.csv');
    }
}
