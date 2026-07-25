<?php

namespace Tests\Feature;

use App\Filament\Pages\System\FailedJobsPage;
use App\Models\Admin;
use App\Models\FailedJob;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FailedJobsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\RolesSeeder::class,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function adminWithRole(string $role): Admin
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole($role);

        return $admin;
    }

    private function makeFailedJob(string $jobClass, string $exception, string $queue = 'default'): FailedJob
    {
        return FailedJob::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'connection' => 'redis',
            'queue' => $queue,
            'payload' => json_encode([
                'displayName' => $jobClass,
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'data' => ['commandName' => $jobClass],
            ]),
            'exception' => $exception,
            'failed_at' => now(),
        ]);
    }

    #[Test]
    public function a_super_admin_can_open_the_page(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        $this->get(FailedJobsPage::getUrl())->assertSuccessful()->assertSee('Failed Jobs');
    }

    #[Test]
    public function a_non_super_admin_is_forbidden(): void
    {
        $this->actingAs($this->adminWithRole('support'), 'admin');

        $this->get(FailedJobsPage::getUrl())->assertForbidden();
    }

    #[Test]
    public function the_job_column_shows_the_class_basename_not_the_uuid(): void
    {
        $this->makeFailedJob('App\\Jobs\\SendWelcomeEmail', 'Connection refused');
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        // The bug being fixed: the Job column used to show a truncated UUID
        // instead of the class name. The FQCN legitimately still appears
        // elsewhere in the DOM (Filament's group-key attribute for
        // collapse/expand JS), so this only asserts the visible name shows.
        $this->get(FailedJobsPage::getUrl())
            ->assertSuccessful()
            ->assertSee('SendWelcomeEmail');
    }

    #[Test]
    public function the_summary_counts_total_and_distinct_job_classes(): void
    {
        $this->makeFailedJob('App\\Jobs\\SendWelcomeEmail', 'Connection refused');
        $this->makeFailedJob('App\\Jobs\\SendWelcomeEmail', 'Connection refused');
        $this->makeFailedJob('App\\Jobs\\ProcessOrderWebhook', 'Call to a member function items() on null');
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        $page = new FailedJobsPage();
        $summary = $page->getFailedJobsSummary();

        $this->assertSame(3, $summary['total']);
        $this->assertSame(2, $summary['distinctJobClasses']);
        $this->assertNotNull($summary['oldestAgo']);
    }

    #[Test]
    public function search_finds_a_job_by_exception_substring(): void
    {
        $this->makeFailedJob('App\\Jobs\\SendWelcomeEmail', 'Connection refused by upstream host');
        $this->makeFailedJob('App\\Jobs\\ProcessOrderWebhook', 'Call to a member function items() on null');
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        Livewire::test(FailedJobsPage::class)
            ->searchTable('Connection refused')
            ->assertCanSeeTableRecords(FailedJob::where('exception', 'like', '%Connection refused%')->get())
            ->assertCanNotSeeTableRecords(FailedJob::where('exception', 'like', '%member function%')->get());
    }

    #[Test]
    public function bulk_retry_removes_selected_rows_from_failed_jobs(): void
    {
        // queue:retry resolves the connection by the failed job's own stored
        // `connection` column (here 'redis') — NOT the test env's QUEUE_CONNECTION
        // override — so without faking it this would push a real job onto the
        // actual Redis queue this dev environment's persistent worker consumes.
        Queue::fake();

        $jobOne = $this->makeFailedJob('App\\Jobs\\SendWelcomeEmail', 'Connection refused');
        $jobTwo = $this->makeFailedJob('App\\Jobs\\ProcessOrderWebhook', 'Call to a member function items() on null');
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        Livewire::test(FailedJobsPage::class)
            ->callTableBulkAction('retrySelected', [$jobOne, $jobTwo]);

        $this->assertSame(0, FailedJob::count());
    }

    #[Test]
    public function retry_all_empties_the_failed_jobs_table(): void
    {
        Queue::fake(); // see note on bulk_retry_removes_selected_rows_from_failed_jobs()

        $this->makeFailedJob('App\\Jobs\\SendWelcomeEmail', 'Connection refused');
        $this->makeFailedJob('App\\Jobs\\ProcessOrderWebhook', 'Call to a member function items() on null');
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        Livewire::test(FailedJobsPage::class)->callTableAction('retryAll');

        $this->assertSame(0, FailedJob::count());
    }

    #[Test]
    public function flush_all_still_deletes_every_failed_job(): void
    {
        $this->makeFailedJob('App\\Jobs\\SendWelcomeEmail', 'Connection refused');
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        Livewire::test(FailedJobsPage::class)->callTableAction('flushAll');

        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    #[Test]
    public function classify_exception_matches_known_keywords_and_returns_null_otherwise(): void
    {
        $this->assertSame('Retry likely', FailedJobsPage::classifyException('Connection refused by upstream')['label']);
        $this->assertSame('Needs code fix', FailedJobsPage::classifyException('TypeError: foo')['label']);
        $this->assertSame('Needs code fix', FailedJobsPage::classifyException('Class App\\Jobs\\Missing not found')['label']);
        $this->assertNull(FailedJobsPage::classifyException('A perfectly ordinary but unrelated message'));
    }
}
