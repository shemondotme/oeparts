<?php

namespace Tests\Feature\Migration;

use App\Services\Migration\ExitCode;
use App\Services\Migration\Import\ImportInbox;
use App\Services\Migration\Transport\HostResolver;
use App\Services\Migration\Transport\IpClassifier;
use App\Services\Migration\Transport\SafeHttpFetcher;
use App\Services\Migration\Transport\TransferDriver;
use App\Services\Migration\Transport\TransferManager;
use App\Services\Migration\Transport\TransferStore;
use App\Services\Migration\Transport\UrlPolicy;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Support\FakeHostResolver;
use Tests\Support\FakeRangeServer;
use Tests\TestCase;

/** The command-line side of the download layer: fetch, list, limits, cancel, and their exit codes. */
class TransferCommandsTest extends TestCase
{
    private const URL = 'https://files.example.com/pkg/site.oeparts';

    private string $dir = '';

    private string $body = '';

    private FakeRangeServer $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oeparts-transfercmd-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/inbox', 0700, true);
        config(['migration.transport.range_bytes' => 65536, 'migration.transport.backoff_seconds' => [600], 'migration.transport.max_attempts' => 3]);
        $this->body = 'OEPARTS1'.random_bytes(200_000);
        $this->server = new FakeRangeServer($this->body);

        $resolver = new FakeHostResolver;
        $policy = new UrlPolicy($resolver, new IpClassifier);
        $this->app->instance(HostResolver::class, $resolver);
        $this->app->instance(TransferStore::class, new TransferStore($this->dir.'/transfers'));
        $this->app->instance(ImportInbox::class, new ImportInbox($this->dir.'/inbox'));
        $this->app->instance(SafeHttpFetcher::class, new SafeHttpFetcher($policy, $this->server->handler()));
        $this->app->forgetInstance(TransferManager::class);
        // waiting out a pause must not cost real seconds in a test
        $this->app->bind(TransferDriver::class, fn () => new TransferDriver(app(TransferManager::class), static fn () => null));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/{inbox,transfers}/{,*/}*', GLOB_BRACE) ?: [] as $file) {
            is_file($file) ? @unlink($file) : null;
        }

        foreach (glob($this->dir.'/transfers/*', GLOB_ONLYDIR) ?: [] as $sub) {
            @rmdir($sub);
        }

        @rmdir($this->dir.'/transfers');
        @rmdir($this->dir.'/inbox');
        @rmdir($this->dir);

        parent::tearDown();
    }

    /** @return array{0: int, 1: string} */
    private function cli(string $command, array $args = []): array
    {
        $buffer = new BufferedOutput;
        $exit = Artisan::call($command, $args + ['--no-interaction' => true], $buffer);

        return [$exit, $buffer->fetch()];
    }

    private function inboxFiles(): array
    {
        return array_map('basename', glob($this->dir.'/inbox/*') ?: []);
    }

    /** @return list<array<string, mixed>> */
    private function events(string $text): array
    {
        return array_values(array_filter(array_map(static fn (string $line) => json_decode($line, true), explode("\n", trim($text))), 'is_array'));
    }

    #[Test]
    public function fetch_brings_the_package_into_the_import_folder_and_exits_zero(): void
    {
        [$exit, $text] = $this->cli('oeparts:migrate:fetch', ['link' => self::URL, '--sha256' => hash('sha256', $this->body)]);

        $this->assertSame(ExitCode::Ok->value, $exit, $text);
        $this->assertSame(['site.oeparts'], $this->inboxFiles());
        $this->assertSame($this->body, file_get_contents($this->dir.'/inbox/site.oeparts'));
        $this->assertStringContainsString('files.example.com', $text);
    }

    #[Test]
    public function fetch_json_prints_events_and_a_result_line_with_the_package_name(): void
    {
        [$exit, $text] = $this->cli('oeparts:migrate:fetch', ['link' => self::URL.'#sha256='.hash('sha256', $this->body), '--json' => true]);

        $this->assertSame(0, $exit, $text);
        $events = $this->events($text);
        $result = end($events);

        $this->assertSame('result', $result['event']);
        $this->assertTrue($result['ok']);
        $this->assertSame('site.oeparts', $result['package']);
        $this->assertContains('transfer', array_column($events, 'event'));
        $this->assertContains('progress', array_column($events, 'event'));
    }

    #[Test]
    public function a_wrong_checksum_exits_four_and_leaves_nothing_in_the_inbox(): void
    {
        [$exit, $text] = $this->cli('oeparts:migrate:fetch', ['link' => self::URL, '--sha256' => str_repeat('a', 64)]);

        $this->assertSame(ExitCode::PackageUnreadable->value, $exit);
        $this->assertStringContainsString('File changed on the way', $text);
        $this->assertSame([], $this->inboxFiles());
    }

    #[Test]
    public function a_missing_file_exits_one(): void
    {
        $this->server->script = [new Response(404, [], 'gone')];

        [$exit] = $this->cli('oeparts:migrate:fetch', ['link' => self::URL]);

        $this->assertSame(ExitCode::Failed->value, $exit);
        $this->assertSame([], $this->inboxFiles());
    }

    #[Test]
    public function a_link_that_is_not_allowed_exits_one_without_starting_a_transfer(): void
    {
        [$exit, $text] = $this->cli('oeparts:migrate:fetch', ['link' => 'http://files.example.com/a.oeparts']);

        $this->assertSame(ExitCode::Failed->value, $exit);
        $this->assertStringContainsString('What to do', $text);
        $this->assertSame([], app(TransferStore::class)->all());
    }

    #[Test]
    public function without_a_link_or_resume_it_is_a_usage_error(): void
    {
        [$exit] = $this->cli('oeparts:migrate:fetch');

        $this->assertSame(ExitCode::Usage->value, $exit);
    }

    #[Test]
    public function a_download_that_is_still_waiting_when_the_budget_ends_exits_ten_and_resume_finishes_it(): void
    {
        $this->server->script = [new Response(206, ['Content-Range' => 'bytes 0-7/'.strlen($this->body), 'Content-Length' => '8'], 'OEPARTS1'), new Response(503, [], 'busy')];

        [$exit, $text] = $this->cli('oeparts:migrate:fetch', ['link' => self::URL, '--budget' => 1]);

        $this->assertSame(ExitCode::NotFinished->value, $exit, $text);
        $this->assertMatchesRegularExpression('/--resume=([a-z0-9]+)/', $text);
        preg_match('/--resume=([a-z0-9]+)/', $text, $m);

        // the pause is 600 s: the clock moves on, the server is healthy again
        $this->travel(601)->seconds();
        [$exit, $text] = $this->cli('oeparts:migrate:fetch', ['--resume' => $m[1]]);

        $this->assertSame(ExitCode::Ok->value, $exit, $text);
        $this->assertSame($this->body, file_get_contents($this->dir.'/inbox/site.oeparts'));
        $this->travelBack();
    }

    #[Test]
    public function list_shows_the_downloads_and_the_packages_in_the_inbox(): void
    {
        $this->cli('oeparts:migrate:fetch', ['link' => self::URL]);
        file_put_contents($this->dir.'/inbox/other.oeparts', 'OEPARTS1x');

        [$exit, $text] = $this->cli('oeparts:migrate:list', ['--json' => true]);

        $this->assertSame(0, $exit);
        $kinds = array_count_values(array_column($this->events($text), 'event'));
        $this->assertSame(1, $kinds['transfer']);
        $this->assertSame(2, $kinds['package']);

        [, $plain] = $this->cli('oeparts:migrate:list');
        $this->assertStringContainsString('other.oeparts', $plain);
        $this->assertStringContainsString('ready', $plain);
    }

    #[Test]
    public function limits_shows_the_numbers_and_the_free_space(): void
    {
        [$exit, $text] = $this->cli('oeparts:migrate:limits', ['--json' => true]);

        $events = $this->events($text);
        $limits = end($events)['limits'] ?? [];
        $this->assertSame(0, $exit);
        $this->assertArrayHasKey('max_package_bytes', $limits);
        $this->assertArrayHasKey('free_bytes', $limits);
        $this->assertSame(65536, $limits['bytes_per_request']);
    }

    #[Test]
    public function cancel_stops_a_download_and_an_unknown_id_is_a_usage_error(): void
    {
        $manager = app(TransferManager::class);
        $state = $manager->start(self::URL);

        [$exit, $text] = $this->cli('oeparts:migrate:cancel', ['id' => $state->id]);

        $this->assertSame(0, $exit, $text);
        $this->assertSame('cancelled', $manager->require($state->id)->status->value);

        [$exit] = $this->cli('oeparts:migrate:cancel', ['id' => 'abcdefabcdefabcdef']);
        $this->assertSame(ExitCode::Usage->value, $exit);
    }
}
