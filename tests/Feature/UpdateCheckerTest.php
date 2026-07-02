<?php

namespace Tests\Feature;

use App\Services\Updates\UpdateChecker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Update & Recovery System (Module 21) — Chunk 1.1 UpdateChecker.
 * No DB needed. Remote HTTP is faked; currentVersion() is mocked so tests are
 * independent of the repo's actual version.json.
 */
class UpdateCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('updates.enabled', true);
        config()->set('updates.channel', 'stable');
        config()->set('updates.check.catalog_url', 'https://updates.test/releases.json');
        config()->set('updates.check.manifest_url', 'https://updates.test/version.json');
        config()->set('updates.check.cache_ttl', 3600);
        config()->set('updates.check.timeout', 5);

        Cache::forget(UpdateChecker::CACHE_KEY);
    }

    private function checkerWithCurrent(string $current): UpdateChecker
    {
        $checker = Mockery::mock(UpdateChecker::class)->makePartial();
        $checker->shouldReceive('currentVersion')->andReturn($current);

        return $checker;
    }

    private function fakeCatalog(array $releases): void
    {
        Http::fake([
            'updates.test/*' => Http::response(['channel' => 'stable', 'releases' => $releases], 200),
        ]);
    }

    #[Test]
    public function it_detects_an_available_update_with_a_single_step_path(): void
    {
        $this->fakeCatalog([
            ['version' => '1.0.0', 'min_version_to_update_from' => '1.0.0'],
            ['version' => '1.2.0', 'min_version_to_update_from' => '1.0.0', 'security' => true,
             'download_url' => 'https://x/oeparts.zip', 'sha256' => 'abc', 'migration_count' => 2],
        ]);

        $status = $this->checkerWithCurrent('1.0.0')->check(true);

        $this->assertTrue($status->updateAvailable);
        $this->assertSame('1.2.0', $status->latestVersion);
        $this->assertTrue($status->security);
        $this->assertSame(2, $status->migrationCount);
        $this->assertSame(['1.2.0'], $status->upgradePath);
        $this->assertFalse($status->isMultiStep());
        $this->assertTrue($status->reachable);
    }

    #[Test]
    public function it_reports_no_update_when_current_is_latest(): void
    {
        $this->fakeCatalog([
            ['version' => '1.0.0'],
            ['version' => '1.2.0'],
        ]);

        $status = $this->checkerWithCurrent('1.2.0')->check(true);

        $this->assertFalse($status->updateAvailable);
        $this->assertSame('1.2.0', $status->latestVersion);
        $this->assertSame([], $status->upgradePath);
    }

    #[Test]
    public function it_resolves_a_multi_step_path_when_a_hop_requires_it(): void
    {
        $this->fakeCatalog([
            ['version' => '1.0.0', 'min_version_to_update_from' => '1.0.0'],
            ['version' => '1.1.0', 'min_version_to_update_from' => '1.0.0'],
            ['version' => '2.0.0', 'min_version_to_update_from' => '1.1.0'], // breaking: needs 1.1.0 first
        ]);

        $status = $this->checkerWithCurrent('1.0.0')->check(true);

        $this->assertTrue($status->updateAvailable);
        $this->assertSame('2.0.0', $status->latestVersion);
        $this->assertSame(['1.1.0', '2.0.0'], $status->upgradePath);
        $this->assertTrue($status->isMultiStep());
    }

    #[Test]
    public function it_degrades_gracefully_when_the_server_is_unreachable(): void
    {
        Http::fake(['updates.test/*' => Http::response('', 500)]);

        $status = $this->checkerWithCurrent('1.0.0')->check(true);

        $this->assertFalse($status->updateAvailable);
        $this->assertFalse($status->reachable);
        $this->assertNotNull($status->error);
        $this->assertSame('1.0.0', $status->currentVersion);
    }

    #[Test]
    public function it_caches_the_result_and_force_bypasses_the_cache(): void
    {
        $this->fakeCatalog([
            ['version' => '1.0.0'],
            ['version' => '1.2.0', 'min_version_to_update_from' => '1.0.0'],
        ]);

        $checker = $this->checkerWithCurrent('1.0.0');

        $checker->check(true);  // hits network
        $checker->check();      // served from cache — no network
        $checker->check(true);  // forced — hits network again

        Http::assertSentCount(2);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
