<?php

namespace Tests\Unit;

use App\Services\CrawlerVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CrawlerVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * PHPUnit can't mock PHP's built-in gethostbyaddr()/gethostbyname()
     * directly — the service wraps them in protected methods specifically
     * so a test double can stub them instead.
     */
    private function serviceStubbingDns(string $reverseResult, string $forwardResult): CrawlerVerificationService
    {
        return new class ($reverseResult, $forwardResult) extends CrawlerVerificationService {
            public function __construct(
                private string $reverseResult,
                private string $forwardResult,
            ) {}

            protected function reverseLookup(string $ip): string
            {
                return $this->reverseResult;
            }

            protected function forwardLookup(string $hostname): string
            {
                return $this->forwardResult;
            }
        };
    }

    #[Test]
    public function verified_googlebot_hostname_that_forward_resolves_back_returns_true(): void
    {
        $service = $this->serviceStubbingDns('crawl-66-249-66-1.googlebot.com', '66.249.66.1');

        $this->assertTrue($service->isVerifiedCrawler('66.249.66.1'));
    }

    #[Test]
    public function spoofed_hostname_that_does_not_forward_resolve_back_returns_false(): void
    {
        // Reverse DNS claims googlebot.com, but forward-resolving that name
        // lands on a DIFFERENT ip than the one that made the request — this
        // is exactly the spoofing scenario the forward-confirm step exists
        // to catch (an attacker can fake their own PTR record, but can't
        // make Google's real DNS forward-resolve it back to their own IP).
        $service = $this->serviceStubbingDns('fake.googlebot.com', '203.0.113.9');

        $this->assertFalse($service->isVerifiedCrawler('198.51.100.5'));
    }

    #[Test]
    public function non_crawler_hostname_returns_false(): void
    {
        $service = $this->serviceStubbingDns('some-residential-isp.example.net', '198.51.100.5');

        $this->assertFalse($service->isVerifiedCrawler('198.51.100.5'));
    }

    #[Test]
    public function unresolvable_reverse_lookup_returns_false(): void
    {
        // gethostbyaddr() returns the IP unchanged when it can't resolve.
        $service = $this->serviceStubbingDns('198.51.100.5', '');

        $this->assertFalse($service->isVerifiedCrawler('198.51.100.5'));
    }

    #[Test]
    public function verified_bingbot_hostname_returns_true(): void
    {
        $service = $this->serviceStubbingDns('msnbot-40-77-167-1.search.msn.com', '40.77.167.1');

        $this->assertTrue($service->isVerifiedCrawler('40.77.167.1'));
    }

    #[Test]
    public function dns_exception_during_lookup_resolves_to_false_never_throws(): void
    {
        $service = new class extends CrawlerVerificationService {
            protected function reverseLookup(string $ip): string
            {
                throw new \RuntimeException('DNS resolver unreachable');
            }
        };

        $this->assertFalse($service->isVerifiedCrawler('198.51.100.5'));
    }

    #[Test]
    public function empty_ip_returns_false(): void
    {
        $service = $this->serviceStubbingDns('crawl.googlebot.com', '');

        $this->assertFalse($service->isVerifiedCrawler(''));
    }

    #[Test]
    public function result_is_cached_per_ip(): void
    {
        $service = $this->serviceStubbingDns('crawl-1.googlebot.com', '198.51.100.5');

        $first = $service->isVerifiedCrawler('198.51.100.5');

        // A second service instance with DIFFERENT (wrong) stubbed DNS
        // results should still read the cached true from the first call —
        // proving the result is genuinely cached per-IP, not re-resolved.
        $secondService = $this->serviceStubbingDns('not-a-crawler.example.net', '203.0.113.1');
        $second = $secondService->isVerifiedCrawler('198.51.100.5');

        $this->assertTrue($first);
        $this->assertTrue($second);
    }
}
