<?php

namespace Tests\Feature\Migration;

use App\Services\Migration\Export\MigrationWorkerProbe;
use App\Services\Migration\Import\ImportInbox;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Transport\IpClassifier;
use App\Services\Migration\Transport\RunPackageTransferJob;
use App\Services\Migration\Transport\SafeHttpFetcher;
use App\Services\Migration\Transport\TransferDriver;
use App\Services\Migration\Transport\TransferLimits;
use App\Services\Migration\Transport\TransferManager;
use App\Services\Migration\Transport\TransferState;
use App\Services\Migration\Transport\TransferStatus;
use App\Services\Migration\Transport\TransferStore;
use App\Services\Migration\Transport\UrlPolicy;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakeHostResolver;
use Tests\Support\FakeRangeServer;
use Tests\TestCase;

/**
 * The download state machine against a file server that lives inside a Guzzle handler: the whole path from a link to a
 * package in the inbox, every way it can stop, and that it carries on from the bytes really on disk.
 */
class TransferManagerTest extends TestCase
{
    private const URL = 'https://files.example.com/pkg/site.oeparts';

    private string $dir = '';

    private string $body = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oeparts-transfer-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/inbox', 0700, true);
        config(['migration.transport.range_bytes' => 65536, 'migration.transport.backoff_seconds' => [5, 15], 'migration.transport.max_attempts' => 3]);
        $this->body = 'OEPARTS1'.random_bytes(300_000);
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        foreach ([$this->dir.'/inbox', $this->dir.'/transfers'] as $folder) {
            foreach (glob($folder.'/{,*/}{,.}*', GLOB_BRACE) ?: [] as $file) {
                is_file($file) ? @unlink($file) : null;
            }

            foreach (glob($folder.'/*', GLOB_ONLYDIR) ?: [] as $sub) {
                @rmdir($sub);
            }

            @rmdir($folder);
        }

        @rmdir($this->dir);

        parent::tearDown();
    }

    private function manager(FakeRangeServer $server, ?TransferLimits $limits = null): TransferManager
    {
        $policy = new UrlPolicy(new FakeHostResolver, new IpClassifier);

        return new TransferManager(
            new TransferStore($this->dir.'/transfers'),
            $policy,
            new SafeHttpFetcher($policy, $server->handler()),
            $limits ?? new TransferLimits,
            new ImportInbox($this->dir.'/inbox'),
        );
    }

    private function inboxFiles(): array
    {
        return array_map('basename', glob($this->dir.'/inbox/*') ?: []);
    }

    // ---- starting -------------------------------------------------------------------------------------------------

    #[Test]
    public function a_started_transfer_keeps_its_address_encrypted_and_reads_the_share_string(): void
    {
        $manager = $this->manager(new FakeRangeServer($this->body));
        $sha = hash('sha256', $this->body);

        $state = $manager->start(self::URL.'?token=SECRET#sha256='.$sha.'&size='.strlen($this->body).'&name=moved.oeparts');

        $this->assertSame(TransferStatus::Queued, $state->status);
        $this->assertSame($sha, $state->expectedSha256);
        $this->assertSame(strlen($this->body), $state->expectedSize);
        $this->assertSame('moved.oeparts', $state->name);
        $this->assertSame('files.example.com', $state->host);

        $raw = (string) file_get_contents($this->dir.'/transfers/'.$state->id.'/state.json');
        $this->assertStringNotContainsString('SECRET', $raw, 'the secret in the address is never written in the clear');
        $this->assertStringNotContainsString('files.example.com/pkg', $raw);
    }

    #[Test]
    public function a_name_is_made_up_when_the_address_has_none_that_would_do(): void
    {
        $manager = $this->manager(new FakeRangeServer($this->body));

        $this->assertMatchesRegularExpression('/^fetched-\d{8}-\d{6}-[0-9a-f]{6}\.oeparts$/', $manager->start('https://files.example.com/download?id=3')->name);
        $this->assertSame('site.oeparts', $manager->start(self::URL)->name);
        $this->assertSame('fetched', substr($manager->start('https://files.example.com/x.oeparts', null, '../../evil.php')->name === 'x.oeparts' ? 'fetched' : 'bad', 0, 7));
    }

    #[Test]
    public function a_link_that_is_not_allowed_or_a_bad_checksum_never_creates_a_transfer(): void
    {
        $manager = $this->manager(new FakeRangeServer($this->body));

        foreach (['http://files.example.com/a.oeparts', 'https://127.0.0.1/a.oeparts', 'https://2130706433/a.oeparts', 'https://user@files.example.com/a.oeparts'] as $link) {
            try {
                $manager->start($link);
                $this->fail("expected refusal of {$link}");
            } catch (MigrationException) {
                $this->addToAssertionCount(1);
            }
        }

        try {
            $manager->start(self::URL, 'not-a-checksum');
            $this->fail('expected refusal');
        } catch (MigrationException $e) {
            $this->assertSame(MigrationErrorCode::PackageHashMismatch, $e->errorCode);
        }

        $this->assertSame([], $manager->store()->all());
    }

    // ---- a whole download ----------------------------------------------------------------------------------------

    #[Test]
    public function a_package_is_downloaded_in_ranges_checked_and_placed_in_the_inbox(): void
    {
        $server = new FakeRangeServer($this->body, issueClaim: true);
        $manager = $this->manager($server);
        $state = $manager->start(self::URL.'#sha256='.hash('sha256', $this->body));

        $done = $manager->advance($state->id, 60.0);

        $this->assertSame(TransferStatus::Ready, $done->status, (string) json_encode($done->error));
        $this->assertSame(['site.oeparts'], $this->inboxFiles());
        $this->assertSame($this->body, file_get_contents($this->dir.'/inbox/site.oeparts'));
        $this->assertSame('site.oeparts', $done->finalName);
        $this->assertNull($done->claimEncrypted, 'the claim is not kept once the transfer is over');
        $this->assertFileDoesNotExist($this->dir.'/transfers/'.$state->id.'/data.part');
        $this->assertGreaterThan(4, count($server->requests), '300 KB in ranges of 64 KB');
        $this->assertSame(0, $manager->progress($done)['attempts']);
        $this->assertSame(100, $manager->progress($done)['percent']);
    }

    #[Test]
    public function the_claim_is_saved_before_any_byte_and_sent_with_every_later_request(): void
    {
        $server = new FakeRangeServer($this->body, issueClaim: true, claim: 'the-claim');
        $manager = $this->manager($server);
        $state = $manager->start(self::URL);

        $manager->advance($state->id, 0.0); // resolving
        $afterFirst = $manager->advance($state->id, 0.0); // the first range

        $this->assertNotNull($afterFirst->claimEncrypted);
        $this->assertNotSame('the-claim', $afterFirst->claimEncrypted);

        $manager->advance($state->id, 60.0);

        $this->assertSame('', $server->requests[0]['claim'], 'the first request has none to send');

        foreach (array_slice($server->requests, 1) as $request) {
            $this->assertSame('the-claim', $request['claim']);
        }

        $this->assertSame(TransferStatus::Ready, $manager->require($state->id)->status);
    }

    #[Test]
    public function a_download_carries_on_from_the_bytes_on_disk_after_a_stop_in_the_middle_of_a_range(): void
    {
        $server = new FakeRangeServer($this->body);
        $state = $this->manager($server)->start(self::URL.'#sha256='.hash('sha256', $this->body));

        // two steps' worth of work, then "the process dies" part way through a range: the file ends mid-range
        $this->manager($server)->advance($state->id, 0.0);
        $this->manager($server)->advance($state->id, 0.0);
        $part = $this->dir.'/transfers/'.$state->id.'/data.part';
        $this->assertFileExists($part);
        $handle = fopen($part, 'r+b');
        ftruncate($handle, 40_000);
        fclose($handle);

        $final = $this->manager($server)->advance($state->id, 60.0);

        $this->assertSame(TransferStatus::Ready, $final->status, (string) json_encode($final->error));
        $this->assertSame($this->body, file_get_contents($this->dir.'/inbox/site.oeparts'));
        $this->assertContains('bytes=40000-105535', array_column($server->requests, 'range'), 'asked for exactly what was missing');
    }

    #[Test]
    public function a_part_longer_than_the_file_is_cut_back_to_the_real_size(): void
    {
        $server = new FakeRangeServer($this->body);
        $manager = $this->manager($server);
        $state = $manager->start(self::URL.'#sha256='.hash('sha256', $this->body));
        $manager->advance($state->id, 0.0);
        $manager->advance($state->id, 0.0);

        // the whole file plus junk (a write that outlived its bookkeeping)
        file_put_contents($this->dir.'/transfers/'.$state->id.'/data.part', $this->body.'JUNK');
        $manager->store()->update($state->id, fn (TransferState $s) => $s->total = strlen($this->body));

        $final = $manager->advance($state->id, 60.0);

        $this->assertSame(TransferStatus::Ready, $final->status, (string) json_encode($final->error));
        $this->assertSame($this->body, file_get_contents($this->dir.'/inbox/site.oeparts'));
    }

    #[Test]
    public function a_small_whole_file_answered_without_ranges_is_accepted(): void
    {
        $small = 'OEPARTS1'.str_repeat('x', 1000);
        $manager = $this->manager(new FakeRangeServer($small, ignoreRange: true));
        $state = $manager->start(self::URL.'#sha256='.hash('sha256', $small));

        $this->assertSame(TransferStatus::Ready, $manager->advance($state->id, 30.0)->status);
        $this->assertSame($small, file_get_contents($this->dir.'/inbox/site.oeparts'));
    }

    #[Test]
    public function without_a_checksum_the_file_is_placed_after_the_package_marker_check(): void
    {
        $manager = $this->manager(new FakeRangeServer($this->body));
        $state = $manager->start(self::URL);

        $this->assertSame(TransferStatus::Ready, $manager->advance($state->id, 60.0)->status);
    }

    #[Test]
    public function the_checksum_pass_works_in_slices_and_resumes_between_calls(): void
    {
        config(['migration.transport.verify_slice_bytes' => 1024 * 1024, 'migration.transport.range_bytes' => 4 * 1024 * 1024]);
        $big = 'OEPARTS1'.random_bytes(3_500_000);
        $manager = $this->manager(new FakeRangeServer($big));
        $state = $manager->start(self::URL.'#sha256='.hash('sha256', $big));

        $seen = [];

        for ($i = 0; $i < 20 && ! $manager->require($state->id)->status->isTerminal(); $i++) {
            $seen[] = $manager->advance($state->id, 0.0)->status->value;
        }

        $this->assertContains('verifying', $seen);
        $this->assertGreaterThan(1, count(array_keys($seen, 'verifying', true)), 'verification took more than one call');
        $this->assertSame(TransferStatus::Ready, $manager->require($state->id)->status);
        $this->assertSame($big, file_get_contents($this->dir.'/inbox/site.oeparts'));
    }

    // ---- what is refused ------------------------------------------------------------------------------------------

    #[Test]
    public function a_file_that_does_not_match_its_checksum_is_thrown_away_and_never_reaches_the_inbox(): void
    {
        $manager = $this->manager(new FakeRangeServer($this->body));
        $state = $manager->start(self::URL.'#sha256='.str_repeat('0', 64));

        $final = $manager->advance($state->id, 60.0);

        $this->assertSame(TransferStatus::Failed, $final->status);
        $this->assertSame(MigrationErrorCode::PackageHashMismatch->value, $final->error['code']);
        $this->assertSame([], $this->inboxFiles());
        $this->assertFileDoesNotExist($this->dir.'/transfers/'.$state->id.'/data.part');
    }

    #[Test]
    public function something_that_is_not_a_package_is_refused_on_its_first_bytes(): void
    {
        $server = new FakeRangeServer('<html><body>Please sign in</body></html>'.str_repeat('x', 200_000));
        $manager = $this->manager($server);
        $state = $manager->start(self::URL);

        $final = $manager->advance($state->id, 60.0);

        $this->assertSame(TransferStatus::Failed, $final->status);
        $this->assertSame(MigrationErrorCode::PackageNotOeparts->value, $final->error['code']);
        $this->assertSame([], $this->inboxFiles());
        $this->assertCount(1, $server->requests, 'it stopped at once, not after the whole file');
    }

    #[Test]
    public function a_file_larger_than_the_limit_is_refused_before_any_byte(): void
    {
        config(['migration.transport.max_bytes' => 100_000]);
        $manager = $this->manager(new FakeRangeServer($this->body));
        $state = $manager->start(self::URL);

        $final = $manager->advance($state->id, 60.0);

        $this->assertSame(TransferStatus::Failed, $final->status);
        $this->assertSame(MigrationErrorCode::UrlTooLarge->value, $final->error['code']);
        $this->assertSame([], $this->inboxFiles());
    }

    #[Test]
    public function a_size_that_differs_from_the_announced_one_is_refused(): void
    {
        $manager = $this->manager(new FakeRangeServer($this->body));
        $state = $manager->start(self::URL.'#size=999');

        $final = $manager->advance($state->id, 60.0);

        $this->assertSame(TransferStatus::Failed, $final->status);
        $this->assertSame(MigrationErrorCode::PackageCorrupt->value, $final->error['code']);
    }

    #[Test]
    public function not_enough_disk_space_is_reported_with_the_numbers_before_the_download_starts(): void
    {
        $tight = new class extends TransferLimits
        {
            public function freeBytes(): ?int
            {
                return 1024;
            }
        };
        $manager = $this->manager(new FakeRangeServer($this->body), $tight);
        $state = $manager->start(self::URL);

        $final = $manager->advance($state->id, 60.0);

        $this->assertSame(TransferStatus::Failed, $final->status);
        $this->assertSame(MigrationErrorCode::PreflightDisk->value, $final->error['code']);
        $this->assertArrayHasKey('needed', $final->error['params']);
        $this->assertArrayHasKey('free', $final->error['params']);
    }

    #[Test]
    public function an_error_status_that_cannot_pass_fails_at_once_and_leaves_nothing(): void
    {
        $server = new FakeRangeServer($this->body);
        $server->script = [new Response(404, [], 'gone')];
        $manager = $this->manager($server);
        $state = $manager->start(self::URL);

        $final = $manager->advance($state->id, 60.0);

        $this->assertSame(TransferStatus::Failed, $final->status);
        $this->assertSame(404, $final->error['params']['status']);
        $this->assertSame([], $this->inboxFiles());
    }

    // ---- pausing and trying again -----------------------------------------------------------------------------------

    #[Test]
    public function a_failure_that_can_pass_pauses_the_transfer_and_it_carries_on_after_the_wait(): void
    {
        $server = new FakeRangeServer($this->body);
        $manager = $this->manager($server);
        $state = $manager->start(self::URL);
        $manager->advance($state->id, 0.0); // resolving
        $server->script = [new Response(503, ['Retry-After' => '1'], 'busy')];

        $paused = $manager->advance($state->id, 0.0);

        $this->assertSame(TransferStatus::Paused, $paused->status);
        $this->assertSame(1, $paused->attempts);
        $this->assertNotNull($paused->nextAttemptAt);
        $this->assertSame(TransferStatus::Paused, $manager->advance($state->id, 60.0)->status, 'still inside the wait: nothing is tried');
        $this->assertCount(1, $server->requests);

        $this->travel(6)->seconds();
        $final = $manager->advance($state->id, 60.0);

        $this->assertSame(TransferStatus::Ready, $final->status, (string) json_encode($final->error));
        $this->assertSame(0, $final->attempts, 'a good step clears the count');
    }

    #[Test]
    public function after_the_last_attempt_a_retryable_failure_ends_the_transfer(): void
    {
        $server = new FakeRangeServer($this->body);
        $manager = $this->manager($server);
        $state = $manager->start(self::URL);
        $manager->advance($state->id, 0.0);
        $failure = fn () => new ConnectException('cut', new Request('GET', self::URL), null, ['errno' => 28]);

        foreach ([1, 2] as $attempt) {
            $server->script = [$failure()];
            $this->assertSame(TransferStatus::Paused, $manager->advance($state->id, 0.0)->status);
            $this->travel(20)->seconds();
        }

        $server->script = [$failure()];
        $final = $manager->advance($state->id, 0.0);

        $this->assertSame(TransferStatus::Failed, $final->status);
        $this->assertSame(MigrationErrorCode::UrlTimeout->value, $final->error['code']);
    }

    // ---- driving, cancelling, queue ---------------------------------------------------------------------------------

    #[Test]
    public function a_second_driver_does_not_touch_a_transfer_that_is_being_driven(): void
    {
        $server = new FakeRangeServer($this->body);
        $manager = $this->manager($server);
        $state = $manager->start(self::URL);
        $handle = $manager->store()->lock($state->id);

        $same = $manager->advance($state->id, 60.0);

        $this->assertSame(TransferStatus::Queued, $same->status);
        $this->assertSame([], $server->requests);

        $manager->store()->unlock($handle);
        $this->assertSame(TransferStatus::Ready, $manager->advance($state->id, 60.0)->status);
    }

    #[Test]
    public function cancelling_removes_the_bytes_and_ends_the_transfer(): void
    {
        $manager = $this->manager(new FakeRangeServer($this->body));
        $state = $manager->start(self::URL);
        $manager->advance($state->id, 0.0);
        $manager->advance($state->id, 0.0);

        $cancelled = $manager->cancel($state->id);

        $this->assertSame(TransferStatus::Cancelled, $cancelled->status);
        $this->assertFileDoesNotExist($this->dir.'/transfers/'.$state->id.'/data.part');
        $this->assertSame(TransferStatus::Cancelled, $manager->advance($state->id, 60.0)->status, 'a cancelled transfer stays cancelled');
        $this->assertSame([], $this->inboxFiles());
    }

    #[Test]
    public function a_name_already_in_the_inbox_is_never_overwritten(): void
    {
        file_put_contents($this->dir.'/inbox/site.oeparts', 'OLD');
        $manager = $this->manager(new FakeRangeServer($this->body));
        $state = $manager->start(self::URL);

        $final = $manager->advance($state->id, 60.0);

        $this->assertSame('site-1.oeparts', $final->finalName);
        $this->assertSame('OLD', file_get_contents($this->dir.'/inbox/site.oeparts'));
        $this->assertSame($this->body, file_get_contents($this->dir.'/inbox/site-1.oeparts'));
    }

    #[Test]
    public function a_queue_job_is_started_only_when_a_worker_is_proven_for_the_migration_queue(): void
    {
        Queue::fake();
        $manager = $this->manager(new FakeRangeServer($this->body));
        $state = $manager->start(self::URL);
        $stamp = MigrationWorkerProbe::file();
        $saved = [];

        foreach ([$stamp->path(), $stamp->path().'.prev', $stamp->path().'.lock'] as $file) {
            $saved[$file] = is_file($file) ? file_get_contents($file) : null;
        }

        try {
            config(['queue.default' => 'sync']);
            $stamp->write(['at' => now()->toIso8601String(), 'queue' => 'migration', 'connection' => 'redis']);
            $this->assertFalse($manager->kick($state->id), 'a sync queue would run the download inside the web request');

            config(['queue.default' => 'redis']);
            $stamp->write(['at' => now()->subHour()->toIso8601String(), 'queue' => 'migration', 'connection' => 'redis']);
            $this->assertFalse($manager->kick($state->id), 'no recent proof that a worker serves the migration queue');

            $stamp->write(['at' => now()->toIso8601String(), 'queue' => 'default', 'connection' => 'redis']);
            $this->assertFalse($manager->kick($state->id), 'a worker that only serves another queue proves nothing');
            Queue::assertNothingPushed();

            $stamp->write(['at' => now()->toIso8601String(), 'queue' => 'migration', 'connection' => 'redis']);
            $this->assertTrue($manager->kick($state->id));
            Queue::assertPushedOn('migration', RunPackageTransferJob::class);
        } finally {
            foreach ($saved as $file => $content) {
                $content === null ? @unlink($file) : file_put_contents($file, $content);
            }
        }
    }

    #[Test]
    public function the_job_advances_once_and_puts_itself_back_until_the_transfer_ends(): void
    {
        Queue::fake();
        $server = new FakeRangeServer($this->body);
        $manager = $this->manager($server);
        $state = $manager->start(self::URL);
        config(['migration.transport.job_budget_seconds' => 0]);

        (new RunPackageTransferJob($state->id))->handle($manager, new TransferLimits);
        Queue::assertPushed(RunPackageTransferJob::class, 1);

        $manager->advance($state->id, 60.0);
        (new RunPackageTransferJob($state->id))->handle($manager, new TransferLimits);
        Queue::assertPushed(RunPackageTransferJob::class, 1); // nothing more once it is ready
    }

    #[Test]
    public function the_driver_waits_out_a_pause_and_reports_each_change_once(): void
    {
        $server = new FakeRangeServer($this->body);
        $manager = $this->manager($server);
        $state = $manager->start(self::URL);
        $manager->advance($state->id, 0.0);
        $server->script = [new Response(503, [], 'busy')];
        $waits = [];
        $driver = new TransferDriver($manager, function (int $ms) use (&$waits): void {
            $waits[] = $ms;
            $this->travel(10)->seconds(); // the wait really passes
        });
        $reported = [];

        $final = $driver->drive($state->id, 120.0, function (array $p) use (&$reported): void {
            $reported[] = $p['status'];
        });

        $this->assertSame(TransferStatus::Ready, $final->status);
        $this->assertNotSame([], $waits);
        $this->assertContains('paused', $reported);
        $this->assertSame(array_values(array_unique($reported)), array_values(array_unique($reported)));
        $this->assertSame('ready', end($reported));
    }

    #[Test]
    public function the_driver_stops_when_asked_and_leaves_a_transfer_that_can_carry_on(): void
    {
        $manager = $this->manager(new FakeRangeServer($this->body));
        $state = $manager->start(self::URL);

        $stopped = (new TransferDriver($manager, static fn () => null))->drive($state->id, null, static fn () => null, static fn (): bool => true, 0.0);

        $this->assertFalse($stopped->status->isTerminal());
        $this->assertSame(TransferStatus::Ready, $manager->advance($state->id, 60.0)->status);
    }
}
