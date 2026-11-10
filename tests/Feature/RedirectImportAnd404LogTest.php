<?php

namespace Tests\Feature;

use App\Enums\RedirectType;
use App\Filament\Resources\NotFoundLogResource\Pages\ListNotFoundLogs;
use App\Jobs\ImportRedirectsFromCsv;
use App\Models\Admin;
use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Services\RedirectLoopDetector;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 410 Gone and wildcard / regex rules everywhere a redirect can be created in
 * bulk: the CSV import and the 404 log's quick-fix actions.
 */
class RedirectImportAnd404LogTest extends TestCase
{
    use RefreshDatabase;

    private function import(string $csv, bool $overwrite = false): void
    {
        Storage::fake('local');
        $path = 'imports/redirects/t-'.uniqid().'.csv';
        Storage::disk('local')->put($path, $csv);

        (new ImportRedirectsFromCsv($path, 'Test Admin', $overwrite))->handle(new RedirectLoopDetector);
    }

    // ── CSV import ────────────────────────────────────────────────────────

    #[Test]
    public function a_csv_can_carry_exact_wildcard_regex_and_410_rows(): void
    {
        $this->import(
            "from_url,to_url,type,is_active,match_type\n"
            ."old-page,/new-page,301,1,exact\n"
            ."en/old-shop/*,/en/parts/\$1,301,1,wildcard\n"
            ."en/legacy-(\\d+),/en/parts/L\$1,302,1,regex\n"
            ."en/discontinued,,410,1,exact\n"
            ."en/old-catalog/*,,410,1,wildcard\n"
        );

        $this->assertDatabaseHas('redirects', ['from_url' => 'old-page', 'match_type' => 'exact', 'type' => '301']);
        $this->assertDatabaseHas('redirects', ['from_url' => 'en/old-shop/*', 'match_type' => 'wildcard', 'to_url' => '/en/parts/$1']);
        $this->assertDatabaseHas('redirects', ['from_url' => 'en/legacy-(\\d+)', 'match_type' => 'regex', 'type' => '302']);
        $this->assertDatabaseHas('redirects', ['from_url' => 'en/discontinued', 'type' => '410', 'to_url' => '']);
        $this->assertDatabaseHas('redirects', ['from_url' => 'en/old-catalog/*', 'type' => '410', 'match_type' => 'wildcard']);
    }

    #[Test]
    public function a_file_without_a_match_type_column_behaves_exactly_as_before(): void
    {
        $this->import("from_url,to_url,type,is_active\nold-a,/new-a,301,1\n");

        $this->assertDatabaseHas('redirects', ['from_url' => 'old-a', 'match_type' => 'exact']);
    }

    #[Test]
    public function rows_that_cannot_work_are_skipped_with_a_reason_and_the_rest_still_import(): void
    {
        $this->import(
            "from_url,to_url,type,is_active,match_type\n"
            ."ok-page,/fine,301,1,exact\n"
            ."en/([unclosed,/x,301,1,regex\n"          // not a valid regex
            ."en/*,/en/new/\$1,301,1,wildcard\n"       // matches its own destination
            ."some-page,,301,1,exact\n"                // 301 needs a destination
            ."weird,/x,301,1,fuzzy\n"                  // unknown match type
        );

        $this->assertSame(['ok-page'], Redirect::pluck('from_url')->all());
    }

    #[Test]
    public function a_410_row_needs_no_destination_but_a_301_row_still_does(): void
    {
        $this->import("from_url,to_url,type\ngone-page,,410\nneeds-target,,301\n");

        $this->assertDatabaseHas('redirects', ['from_url' => 'gone-page', 'type' => '410']);
        $this->assertDatabaseMissing('redirects', ['from_url' => 'needs-target']);
    }

    #[Test]
    public function updating_an_existing_row_can_turn_it_into_a_410(): void
    {
        Redirect::create(['from_url' => 'old-line', 'to_url' => '/somewhere', 'type' => RedirectType::Permanent, 'is_active' => true, 'hit_count' => 0]);

        $this->import("from_url,to_url,type\nold-line,,410\n", overwrite: true);

        $this->assertDatabaseHas('redirects', ['from_url' => 'old-line', 'type' => '410', 'to_url' => '']);
        $this->assertSame(1, Redirect::count());
    }

    #[Test]
    public function an_imported_pattern_rule_actually_redirects_a_request(): void
    {
        $this->import("from_url,to_url,type,match_type\nen/old-shop/*,/en/parts/\$1,301,wildcard\nen/retired/*,,410,wildcard\n");

        $this->get('/en/old-shop/AB-1')->assertStatus(301)->assertRedirect('/en/parts/AB-1');
        $this->get('/en/retired/anything')->assertStatus(410);
    }

    // ── 404 log quick-fix ─────────────────────────────────────────────────

    private function admin(): void
    {
        $this->seed(RolesSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');
    }

    #[Test]
    public function a_404_can_be_marked_gone_with_no_destination(): void
    {
        $this->admin();
        $log = NotFoundLog::create(['path' => 'en/old-dead-page', 'path_hash' => hash('sha256', 'en/old-dead-page'), 'hit_count' => 3, 'first_seen_at' => now(), 'last_seen_at' => now(), 'resolved' => false]);

        Livewire::test(ListNotFoundLogs::class)
            ->callTableAction('createRedirect', $log, data: ['type' => RedirectType::Gone->value]);

        $this->assertDatabaseHas('redirects', ['from_url' => 'en/old-dead-page', 'type' => '410', 'to_url' => '']);
        $this->assertTrue((bool) $log->fresh()->resolved);
        $this->get('/en/old-dead-page')->assertStatus(410);
    }

    #[Test]
    public function the_bulk_action_can_retire_many_404s_as_gone_in_one_go(): void
    {
        $this->admin();
        $a = NotFoundLog::create(['path' => 'en/gone-a', 'path_hash' => hash('sha256', 'en/gone-a'), 'hit_count' => 1, 'first_seen_at' => now(), 'last_seen_at' => now(), 'resolved' => false]);
        $b = NotFoundLog::create(['path' => 'en/gone-b', 'path_hash' => hash('sha256', 'en/gone-b'), 'hit_count' => 1, 'first_seen_at' => now(), 'last_seen_at' => now(), 'resolved' => false]);

        Livewire::test(ListNotFoundLogs::class)
            ->callTableBulkAction('bulkCreateRedirect', [$a, $b], data: ['type' => RedirectType::Gone->value]);

        $this->assertSame(2, Redirect::where('type', '410')->count());
    }

    #[Test]
    public function a_301_from_the_404_log_still_needs_a_destination(): void
    {
        $this->admin();
        $log = NotFoundLog::create(['path' => 'en/needs-target', 'path_hash' => hash('sha256', 'en/needs-target'), 'hit_count' => 1, 'first_seen_at' => now(), 'last_seen_at' => now(), 'resolved' => false]);

        Livewire::test(ListNotFoundLogs::class)
            ->callTableAction('createRedirect', $log, data: ['type' => RedirectType::Permanent->value])
            ->assertHasTableActionErrors(['to_url']);

        $this->assertSame(0, Redirect::count());
    }
}
