<?php

namespace Tests\Feature\Migration;

use App\Models\PackageShareLink;
use App\Security\PackageAudit;
use App\Services\Migration\Export\PackageOutbox;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Transport\ShareLinkService;
use App\Services\Migration\Transport\ShareSlots;
use App\Services\Migration\Transport\ShareStreamer;
use App\Services\Migration\Transport\ShareString;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The share link on the SOURCE site: how a link is made, what the endpoint does with it, and that every refusal looks the
 * same. The package lives in a temporary exports folder; nothing here touches the real exports or keys of this machine.
 */
class ShareLinkTest extends TestCase
{
    use RefreshDatabase;

    private string $dir = '';

    private string $body = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oeparts-share-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/exports', 0700, true);
        $this->app->instance(PackageOutbox::class, new PackageOutbox($this->dir.'/exports'));
        $this->app->instance(ShareSlots::class, new ShareSlots($this->dir.'/slots'));
        $this->app->instance(PackageAudit::class, new PackageAudit($this->dir.'/history.ndjson'));
        config(['migration.share.chunk_bytes' => 65536]);
        $this->app->when(ShareStreamer::class)->needs('$closeOutputBuffers')->give(false);

        $this->body = 'OEPARTS1'.random_bytes(250_000);
        file_put_contents($this->dir.'/exports/site.oeparts', $this->body);
        file_put_contents($this->dir.'/exports/site.oeparts.json', json_encode(['sha256' => hash('sha256', $this->body), 'size' => strlen($this->body)]));
        RateLimiter::clear('package-share-deny:127.0.0.1');
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        RateLimiter::clear('package-share-deny:127.0.0.1');

        foreach (glob($this->dir.'/{,*/}{,.}*', GLOB_BRACE) ?: [] as $file) {
            is_file($file) ? @unlink($file) : null;
        }

        foreach (['exports', 'slots'] as $sub) {
            @rmdir($this->dir.'/'.$sub);
        }

        @rmdir($this->dir);

        parent::tearDown();
    }

    private function service(): ShareLinkService
    {
        return app(ShareLinkService::class);
    }

    /** @return array{link: PackageShareLink, token: string, share: string, warning: string|null} */
    private function link(array $args = []): array
    {
        return $this->service()->create('site.oeparts', ...$args);
    }

    private function assertUniformNotFound($response): void
    {
        $response->assertNotFound();
        $this->assertSame('Not found', $response->getContent());
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    // ---- making a link --------------------------------------------------------------------------------------------

    #[Test]
    public function a_link_is_made_for_a_package_and_only_the_hash_of_its_token_is_stored(): void
    {
        $made = $this->link();

        $this->assertSame(43, strlen($made['token']));
        $this->assertSame(hash('sha256', $made['token']), $made['link']->token_hash);
        $this->assertStringNotContainsString($made['token'], (string) json_encode(PackageShareLink::query()->first()->getAttributes()));

        $parsed = ShareString::parse($made['share']);
        $host = parse_url((string) config('app.url'), PHP_URL_HOST).(parse_url((string) config('app.url'), PHP_URL_PORT) ? ':'.parse_url((string) config('app.url'), PHP_URL_PORT) : '');
        $this->assertSame('https://'.$host.'/_pkg/'.$made['token'], $parsed['url']);
        $this->assertSame(hash('sha256', $this->body), $parsed['sha256']);
        $this->assertSame(strlen($this->body), $parsed['size']);
        $this->assertSame('site.oeparts', $parsed['name']);
        $this->assertSame(str_starts_with((string) config('app.url'), 'https://'), $made['warning'] === null);
    }

    #[Test]
    public function the_link_uses_the_canonical_host_when_the_site_has_one_and_warns_about_an_http_address(): void
    {
        app(SettingsService::class)->set('seo.canonical_host', 'shop.example.org');
        config(['app.url' => 'http://localhost:8080']);

        $made = $this->link();

        $this->assertStringStartsWith('https://shop.example.org/_pkg/', $made['share']);
        $this->assertNotNull($made['warning']);
    }

    #[Test]
    public function a_package_that_is_not_in_the_exports_folder_gets_no_link(): void
    {
        foreach (['missing.oeparts', '../site.oeparts', '/etc/passwd', 'site.oeparts.json', 'site.txt'] as $name) {
            try {
                $this->service()->create($name);
                $this->fail("expected refusal of {$name}");
            } catch (MigrationException $e) {
                $this->assertSame(MigrationErrorCode::PackageNotFound, $e->errorCode);
            }
        }

        $this->assertSame(0, PackageShareLink::query()->count());
    }

    #[Test]
    public function the_lifetime_is_capped_and_only_five_links_can_be_live(): void
    {
        $long = $this->link(['ttlHours' => 99999]);
        $this->assertTrue($long['link']->expires_at->lte(now()->addHours(168)->addMinute()));

        for ($i = 0; $i < 4; $i++) {
            $this->link();
        }

        try {
            $this->link();
            $this->fail('the sixth live link must be refused');
        } catch (MigrationException $e) {
            $this->assertSame(MigrationErrorCode::LockBusy, $e->errorCode);
        }

        $this->assertTrue($this->service()->revoke((int) $long['link']->getKey()));
        $this->link(); // a revoked link frees its place
        $this->assertSame(5, $this->service()->liveCount());
    }

    // ---- the endpoint ---------------------------------------------------------------------------------------------

    #[Test]
    public function a_whole_download_hands_over_the_file_and_uses_the_link_up(): void
    {
        $made = $this->link();

        $response = $this->get('/_pkg/'.$made['token']);

        $response->assertOk();
        $this->assertSame($this->body, $response->streamedContent());
        $this->assertSame((string) strlen($this->body), $response->headers->get('Content-Length'));
        $this->assertSame('attachment; filename="site.oeparts"', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertNotEmpty($response->headers->get('X-OeParts-Claim'));
        $this->assertSame(PackageShareLink::CONSUMED, $made['link']->refresh()->state);
        $this->assertSame(strlen($this->body), $made['link']->bytes_served);

        $this->assertUniformNotFound($this->get('/_pkg/'.$made['token']));
    }

    #[Test]
    public function a_download_can_be_resumed_only_with_the_claim_and_ends_when_the_last_byte_is_sent(): void
    {
        $made = $this->link();
        $size = strlen($this->body);

        $first = $this->withHeader('Range', 'bytes=0-9999')->get('/_pkg/'.$made['token']);
        $first->assertStatus(206);
        $claim = (string) $first->headers->get('X-OeParts-Claim');
        $this->assertNotSame('', $claim);
        $this->assertSame('bytes 0-9999/'.$size, $first->headers->get('Content-Range'));
        $this->assertSame(substr($this->body, 0, 10000), $first->streamedContent());
        $this->assertSame(PackageShareLink::CLAIMED, $made['link']->refresh()->state);

        $this->assertUniformNotFound($this->withHeader('Range', 'bytes=10000-')->get('/_pkg/'.$made['token']));
        $this->assertUniformNotFound($this->withHeaders(['Range' => 'bytes=10000-', 'X-OeParts-Claim' => 'wrong'])->get('/_pkg/'.$made['token']));

        $rest = $this->withHeaders(['Range' => 'bytes=10000-', 'X-OeParts-Claim' => $claim])->get('/_pkg/'.$made['token']);
        $rest->assertStatus(206);
        $this->assertNull($rest->headers->get('X-OeParts-Claim'), 'the secret is handed out once');
        $this->assertSame(substr($this->body, 10000), $rest->streamedContent());
        $this->assertSame(PackageShareLink::CONSUMED, $made['link']->refresh()->state);
        $this->assertUniformNotFound($this->withHeaders(['Range' => 'bytes=0-9', 'X-OeParts-Claim' => $claim])->get('/_pkg/'.$made['token']));
    }

    #[Test]
    public function range_forms_are_understood_and_a_start_past_the_end_gets_416_with_the_real_size(): void
    {
        $made = $this->link();
        $size = strlen($this->body);
        $claim = (string) $this->withHeader('Range', 'bytes=0-0')->get('/_pkg/'.$made['token'])->headers->get('X-OeParts-Claim');
        $with = fn (string $range) => $this->withHeaders(['Range' => $range, 'X-OeParts-Claim' => $claim])->get('/_pkg/'.$made['token']);

        $suffix = $with('bytes=-100');
        $suffix->assertStatus(206);
        $this->assertSame(substr($this->body, -100), $suffix->streamedContent());
        $this->assertSame('bytes '.($size - 100).'-'.($size - 1).'/'.$size, $suffix->headers->get('Content-Range'));
        $made['link']->refresh();
        $made['link']->state = PackageShareLink::CLAIMED; // a suffix read reaches the end; a test of other forms needs it live again
        $made['link']->save();

        $past = $with('bytes='.$size.'-');
        $past->assertStatus(416);
        $this->assertSame('bytes */'.$size, $past->headers->get('Content-Range'));

        $several = $with('bytes=0-9,20-29');
        $several->assertOk();
        $this->assertSame($this->body, $several->streamedContent(), 'several ranges are ignored: the whole file is sent');
    }

    #[Test]
    public function every_refusal_looks_the_same_from_outside(): void
    {
        $expired = $this->link();
        $expired['link']->update(['expires_at' => now()->subMinute()]);
        $revoked = $this->link();
        $this->service()->revoke((int) $revoked['link']->getKey());
        $used = $this->link();
        $this->get('/_pkg/'.$used['token'])->streamedContent();
        $bound = $this->link(['boundIp' => '203.0.113.9']);
        $gone = $this->link();
        unlink($this->dir.'/exports/site.oeparts');
        file_put_contents($this->dir.'/exports/site.oeparts', 'tampered: another size');

        $answers = [];

        foreach ([
            'unknown' => str_repeat('a', 43),
            'malformed' => 'short',
            'expired' => $expired['token'],
            'revoked' => $revoked['token'],
            'used' => $used['token'],
            'wrong address' => $bound['token'],
            'file changed' => $gone['token'],
        ] as $why => $token) {
            $response = $this->get('/_pkg/'.$token);
            $response->assertNotFound();
            $answers[$why] = [$response->getStatusCode(), $response->getContent(), $response->headers->get('Content-Type'), $response->headers->get('Cache-Control')];
        }

        $this->assertCount(1, array_unique(array_map('serialize', $answers)), 'no refusal can be told from another: '.json_encode(array_keys($answers)));
    }

    #[Test]
    public function the_real_reason_of_a_refusal_goes_to_the_history_and_never_carries_the_token(): void
    {
        $revoked = $this->link();
        $this->service()->revoke((int) $revoked['link']->getKey());

        $this->get('/_pkg/'.$revoked['token']);
        $this->get('/_pkg/'.$revoked['token']);

        $history = (string) file_get_contents($this->dir.'/history.ndjson');
        $this->assertSame(2, substr_count($history, '"event":"share.denied"'), 'each refusal is a line');
        $this->assertStringContainsString('link is revoked', $history);
        $this->assertStringNotContainsString($revoked['token'], $history);
    }

    #[Test]
    public function after_twenty_refusals_from_one_address_that_address_gets_429(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->get('/_pkg/'.str_repeat('b', 43))->assertNotFound();
        }

        $blocked = $this->get('/_pkg/'.str_repeat('b', 43));
        $blocked->assertStatus(429);
        $this->assertNotNull($blocked->headers->get('Retry-After'));

        $made = $this->link();
        $this->get('/_pkg/'.$made['token'])->assertStatus(429); // even the real token, from that address, for the hour

        $other = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])->get('/_pkg/'.$made['token']);
        $other->assertOk();
        $other->streamedContent();
    }

    #[Test]
    public function head_is_refused_and_does_not_claim_the_link(): void
    {
        $made = $this->link();

        $this->call('HEAD', '/_pkg/'.$made['token'])->assertStatus(405);

        $this->assertSame(PackageShareLink::ACTIVE, $made['link']->refresh()->state);
    }

    #[Test]
    public function a_busy_slot_answers_503_and_leaves_the_link_unclaimed(): void
    {
        $made = $this->link();
        $slots = app(ShareSlots::class);
        $held = $slots->acquire();

        $busy = $this->get('/_pkg/'.$made['token']);
        $busy->assertStatus(503);
        $this->assertSame('30', $busy->headers->get('Retry-After'));
        $this->assertSame(PackageShareLink::ACTIVE, $made['link']->refresh()->state, 'a busy answer must not burn the link');

        $slots->release($held);
        $ok = $this->get('/_pkg/'.$made['token']);
        $ok->assertOk();
        $ok->streamedContent();
    }

    #[Test]
    public function the_number_of_parallel_downloads_can_be_raised_but_never_below_one(): void
    {
        $slots = app(ShareSlots::class);
        config(['migration.share.max_streams' => 0]);
        $one = $slots->acquire();
        $this->assertNotNull($one);
        $this->assertNull($slots->acquire());
        $slots->release($one);

        config(['migration.share.max_streams' => 3]);
        $held = [$slots->acquire(), $slots->acquire(), $slots->acquire()];
        $this->assertNotContains(null, $held);
        $this->assertNull($slots->acquire());

        foreach ($held as $handle) {
            $slots->release($handle);
        }
    }

    #[Test]
    public function an_address_bound_link_answers_only_that_address(): void
    {
        $made = $this->link(['boundIp' => '203.0.113.9']);

        $this->assertUniformNotFound($this->get('/_pkg/'.$made['token']));
        $ok = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->get('/_pkg/'.$made['token']);
        $ok->assertOk();
        $ok->streamedContent();
    }

    #[Test]
    public function two_first_requests_cannot_both_claim_the_link(): void
    {
        $made = $this->link();

        $first = $this->service()->claim($made['token'], null, '127.0.0.1');
        $second = $this->service()->claim($made['token'], null, '127.0.0.1');

        $this->assertTrue($first['ok']);
        $this->assertNotNull($first['claim']);
        $this->assertFalse($second['ok'], 'the second first-request has no claim secret and must be refused');
    }

    #[Test]
    public function a_link_that_ran_out_while_claimed_ends_when_its_claim_window_is_over(): void
    {
        config(['migration.share.claim_window_hours' => 2]);
        $made = $this->link(['ttlHours' => 1]);
        $claim = $this->withHeader('Range', 'bytes=0-9')->get('/_pkg/'.$made['token'])->headers->get('X-OeParts-Claim');

        $this->travel(90)->minutes(); // past the link's own hour, inside the 2-hour claim window
        $this->withHeaders(['Range' => 'bytes=10-19', 'X-OeParts-Claim' => $claim])->get('/_pkg/'.$made['token'])->assertStatus(206);

        $this->travel(60)->minutes(); // now past the claim window too
        $this->assertUniformNotFound($this->withHeaders(['Range' => 'bytes=20-29', 'X-OeParts-Claim' => $claim])->get('/_pkg/'.$made['token']));
        $this->assertSame(PackageShareLink::EXPIRED, $made['link']->refresh()->state);
    }

    #[Test]
    public function a_frozen_source_still_hands_the_package_out(): void
    {
        // the storefront is in maintenance mode and an export freeze is live
        app(SettingsService::class)->set('maintenance.enabled', true);
        $made = $this->link();

        $this->get('/en')->assertStatus(503); // the storefront is closed ...
        $response = $this->get('/_pkg/'.$made['token']); // ... the package route is not

        $response->assertOk();
        $this->assertSame($this->body, $response->streamedContent());
    }

    #[Test]
    public function the_route_carries_no_session_cookie_or_csrf_and_is_not_a_closure(): void
    {
        $route = app('router')->getRoutes()->getByName('migrate.share');

        $this->assertNotNull($route);
        $this->assertIsString($route->getActionName());
        $this->assertStringNotContainsString('Closure', $route->getActionName());
        $middleware = app('router')->gatherRouteMiddleware($route);

        foreach ($middleware as $m) {
            $this->assertStringNotContainsString('VerifyCsrfToken', (string) $m);
            $this->assertStringNotContainsString('StartSession', (string) $m);
            $this->assertStringNotContainsString('EncryptCookies', (string) $m);
            $this->assertStringNotContainsString('RedirectIfNotInstalled', (string) $m);
        }

        $response = $this->get('/_pkg/'.str_repeat('c', 43));
        $this->assertCount(0, $response->headers->getCookies());
    }

    #[Test]
    public function the_table_is_excluded_from_backups_and_belongs_to_the_system_group(): void
    {
        $this->assertContains('package_share_links', (array) config('backup.db.exclude_tables_entirely'));
        $this->assertContains('package_share_links', (new GroupRegistry)->systemTables());
    }
}
