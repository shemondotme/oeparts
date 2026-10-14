<?php

namespace Tests\Feature\Migration;

use App\Services\Migration\Transport\TransferJanitor;
use App\Services\Migration\Transport\TransferState;
use App\Services\Migration\Transport\TransferStatus;
use App\Services\Migration\Transport\TransferStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** What the janitor closes and removes, and that it never touches a download somebody is driving. */
class TransferJanitorTest extends TestCase
{
    use RefreshDatabase;

    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oeparts-janitor-'.bin2hex(random_bytes(4));
        mkdir($this->dir, 0700, true);
        config(['migration.transport.abandoned_hours' => 48, 'migration.transport.retention_days' => 7]);
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        foreach (glob($this->dir.'/*', GLOB_ONLYDIR) ?: [] as $sub) {
            foreach (glob($sub.'/{,.}*', GLOB_BRACE) ?: [] as $file) {
                is_file($file) ? @unlink($file) : null;
            }

            @rmdir($sub);
        }

        @rmdir($this->dir);

        parent::tearDown();
    }

    private function store(): TransferStore
    {
        return new TransferStore($this->dir);
    }

    private function withPart(TransferState $state): void
    {
        file_put_contents($this->store()->partPath($state->id), 'half of a big file');
    }

    #[Test]
    public function a_download_nobody_touched_for_two_days_is_closed_and_its_bytes_are_freed(): void
    {
        $store = $this->store();
        $stale = $store->create(['status' => 'downloading', 'claim' => 'x']);
        $this->withPart($stale);
        $this->travel(49)->hours();
        $fresh = $store->create(['status' => 'downloading']);
        $this->withPart($fresh);

        $report = (new TransferJanitor($store))->run();

        $this->assertSame(['abandoned' => 1, 'removed' => 0], $report);
        $closed = $store->find($stale->id);
        $this->assertSame(TransferStatus::Failed, $closed->status);
        $this->assertNull($closed->claimEncrypted);
        $this->assertFileDoesNotExist($store->partPath($stale->id));
        $this->assertSame(TransferStatus::Downloading, $store->find($fresh->id)->status, 'a recent download is left alone');
        $this->assertFileExists($store->partPath($fresh->id));
    }

    #[Test]
    public function the_record_of_a_finished_download_goes_after_the_retention_days(): void
    {
        $store = $this->store();
        $ready = $store->create(['status' => 'ready']);
        $failed = $store->create(['status' => 'failed']);

        $this->travel(6)->days();
        $this->assertSame(['abandoned' => 0, 'removed' => 0], (new TransferJanitor($store))->run());

        $this->travel(2)->days();
        $this->assertSame(['abandoned' => 0, 'removed' => 2], (new TransferJanitor($store))->run());
        $this->assertNull($store->find($ready->id));
        $this->assertNull($store->find($failed->id));
        $this->assertDirectoryDoesNotExist($this->dir.'/'.$ready->id);
    }

    #[Test]
    public function a_download_that_is_being_driven_is_never_touched(): void
    {
        $store = $this->store();
        $busy = $store->create(['status' => 'downloading']);
        $this->withPart($busy);
        $lock = $store->lock($busy->id);
        $this->travel(100)->hours();

        $report = (new TransferJanitor($store))->run();

        $this->assertSame(['abandoned' => 0, 'removed' => 0], $report);
        $this->assertSame(TransferStatus::Downloading, $store->find($busy->id)->status);
        $this->assertFileExists($store->partPath($busy->id));
        $store->unlock($lock);
    }

    #[Test]
    public function the_janitor_command_reports_the_downloads_too(): void
    {
        $this->app->instance(TransferStore::class, $this->store());
        $this->store()->create(['status' => 'ready']);
        $this->travel(9)->days();

        $this->artisan('oeparts:migrate:janitor')
            ->expectsOutputToContain('1 old download record(s) removed')
            ->assertSuccessful();
    }
}
