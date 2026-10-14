<?php

namespace Tests\Feature\Migration;

use App\Models\PackageShareLink;
use App\Security\PackageAudit;
use App\Services\Migration\Export\PackageOutbox;
use App\Services\Migration\Import\ImportInbox;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\Transport\IpClassifier;
use App\Services\Migration\Transport\RangeFileStreamer;
use App\Services\Migration\Transport\SafeHttpFetcher;
use App\Services\Migration\Transport\ShareLinkService;
use App\Services\Migration\Transport\ShareSlots;
use App\Services\Migration\Transport\TransferLimits;
use App\Services\Migration\Transport\TransferManager;
use App\Services\Migration\Transport\TransferStatus;
use App\Services\Migration\Transport\TransferStore;
use App\Services\Migration\Transport\UrlPolicy;
use App\Services\SettingsService;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Support\FakeHostResolver;
use Tests\TestCase;

/**
 * The two halves of a site move meeting: the TARGET's download manager pulling a package from the SOURCE's real /_pkg route
 * (controller, claim, ranges, limiters, all of it), with the network replaced by an in-process call. It proves that the two
 * sides agree on the claim header, on partial answers and on what "used up" means.
 */
class ShareToTransferTest extends TestCase
{
    use RefreshDatabase;

    private string $dir = '';

    private string $body = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oeparts-s2t-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/exports', 0700, true);
        mkdir($this->dir.'/inbox', 0700, true);
        $this->app->instance(PackageOutbox::class, new PackageOutbox($this->dir.'/exports'));
        $this->app->instance(ShareSlots::class, new ShareSlots($this->dir.'/slots'));
        $this->app->instance(PackageAudit::class, new PackageAudit($this->dir.'/history.ndjson'));
        $this->app->when(RangeFileStreamer::class)->needs('$closeOutputBuffers')->give(false);
        config(['migration.share.chunk_bytes' => 65536, 'migration.transport.range_bytes' => 65536]);
        app(SettingsService::class)->set('seo.canonical_host', 'source.example.com');
        RateLimiter::clear('package-share-deny:127.0.0.1');

        $this->body = 'OEPARTS1'.random_bytes(400_000);
        file_put_contents($this->dir.'/exports/site.oeparts', $this->body);
        file_put_contents($this->dir.'/exports/site.oeparts.json', json_encode(['sha256' => hash('sha256', $this->body), 'size' => strlen($this->body)]));
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('package-share-deny:127.0.0.1');

        foreach (glob($this->dir.'/{,*/}{,.}*', GLOB_BRACE) ?: [] as $file) {
            is_file($file) ? @unlink($file) : null;
        }

        foreach (glob($this->dir.'/*', GLOB_ONLYDIR) ?: [] as $sub) {
            foreach (glob($sub.'/*', GLOB_ONLYDIR) ?: [] as $deeper) {
                foreach (glob($deeper.'/{,.}*', GLOB_BRACE) ?: [] as $file) {
                    is_file($file) ? @unlink($file) : null;
                }

                @rmdir($deeper);
            }

            @rmdir($sub);
        }

        @rmdir($this->dir);

        parent::tearDown();
    }

    /** A Guzzle handler that answers by calling this application's own routes. */
    private function inProcessNetwork(): callable
    {
        return function (RequestInterface $request, array $options) {
            $server = [];

            foreach ($request->getHeaders() as $name => $values) {
                $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = implode(',', $values);
            }

            $uri = $request->getUri();
            $answer = $this->call('GET', (string) $uri->withFragment(''), [], [], [], $server);
            $content = $answer->baseResponse instanceof StreamedResponse ? $answer->streamedContent() : (string) $answer->getContent();

            $headers = [];
            foreach ($answer->headers->allPreserveCase() as $name => $values) {
                $headers[$name] = $values;
            }

            return (new MockHandler([new Response($answer->getStatusCode(), $headers, $content)]))($request, $options);
        };
    }

    private function manager(): TransferManager
    {
        $policy = new UrlPolicy(new FakeHostResolver, new IpClassifier);

        return new TransferManager(
            new TransferStore($this->dir.'/transfers'),
            $policy,
            new SafeHttpFetcher($policy, $this->inProcessNetwork()),
            new TransferLimits,
            new ImportInbox($this->dir.'/inbox'),
        );
    }

    /** @return array{share: string, link: PackageShareLink} */
    private function share(): array
    {
        $made = app(ShareLinkService::class)->create('site.oeparts');

        return ['share' => $made['share'], 'link' => $made['link']];
    }

    #[Test]
    public function a_package_travels_from_the_share_link_into_the_other_sites_inbox_and_the_link_is_used_up(): void
    {
        $made = $this->share();
        $manager = $this->manager();
        $state = $manager->start($made['share']);

        $final = $manager->advance($state->id, 60.0);

        $this->assertSame(TransferStatus::Ready, $final->status, (string) json_encode($final->error));
        $this->assertSame($this->body, file_get_contents($this->dir.'/inbox/site.oeparts'));
        $this->assertSame(PackageShareLink::CONSUMED, $made['link']->refresh()->state);
        $this->assertSame(strlen($this->body), $made['link']->bytes_served);
    }

    #[Test]
    public function the_claim_survives_a_restart_of_the_downloading_side(): void
    {
        $made = $this->share();
        $state = $this->manager()->start($made['share']);

        $this->manager()->advance($state->id, 0.0); // resolving
        $this->manager()->advance($state->id, 0.0); // the first range: the link is claimed
        $this->assertSame(PackageShareLink::CLAIMED, $made['link']->refresh()->state);

        // "the process dies": a new manager object carries on from the saved state alone
        $final = $this->manager()->advance($state->id, 60.0);

        $this->assertSame(TransferStatus::Ready, $final->status, (string) json_encode($final->error));
        $this->assertSame($this->body, file_get_contents($this->dir.'/inbox/site.oeparts'));
        $this->assertSame(PackageShareLink::CONSUMED, $made['link']->refresh()->state);
    }

    #[Test]
    public function a_second_transfer_with_the_same_link_is_refused_and_nothing_lands(): void
    {
        $made = $this->share();
        $first = $this->manager()->start($made['share']);
        $this->assertSame(TransferStatus::Ready, $this->manager()->advance($first->id, 60.0)->status);
        @unlink($this->dir.'/inbox/site.oeparts');

        $second = $this->manager()->start($made['share']);
        $final = $this->manager()->advance($second->id, 60.0);

        $this->assertSame(TransferStatus::Failed, $final->status);
        $this->assertSame(MigrationErrorCode::UrlHttpError->value, $final->error['code']);
        $this->assertSame(404, $final->error['params']['status']);
        $this->assertSame([], glob($this->dir.'/inbox/*'));
    }

    #[Test]
    public function a_checksum_that_does_not_match_the_file_is_caught_on_the_receiving_side(): void
    {
        $made = $this->share();
        $tampered = preg_replace('/sha256=[a-f0-9]{64}/', 'sha256='.str_repeat('0', 64), $made['share']);
        $manager = $this->manager();
        $state = $manager->start((string) $tampered);

        $final = $manager->advance($state->id, 60.0);

        $this->assertSame(TransferStatus::Failed, $final->status);
        $this->assertSame(MigrationErrorCode::PackageHashMismatch->value, $final->error['code']);
        $this->assertSame([], glob($this->dir.'/inbox/*'));
        $this->assertSame(PackageShareLink::CONSUMED, $made['link']->refresh()->state, 'the link is single-use: a new one is needed');
    }

    #[Test]
    public function a_busy_source_pauses_the_transfer_and_it_carries_on_without_burning_the_link(): void
    {
        $made = $this->share();
        $manager = $this->manager();
        $state = $manager->start($made['share']);
        $manager->advance($state->id, 0.0); // resolving

        $held = app(ShareSlots::class)->acquire(); // another download is running on the source
        $paused = $manager->advance($state->id, 0.0);

        $this->assertSame(TransferStatus::Paused, $paused->status, 'a 503 is a failure that can pass');
        $this->assertSame(PackageShareLink::ACTIVE, $made['link']->refresh()->state);

        app(ShareSlots::class)->release($held);
        $this->travel(31)->seconds(); // the source asked to wait 30 seconds
        $final = $manager->advance($state->id, 60.0);

        $this->assertSame(TransferStatus::Ready, $final->status, (string) json_encode($final->error));
        $this->travelBack();
    }
}
