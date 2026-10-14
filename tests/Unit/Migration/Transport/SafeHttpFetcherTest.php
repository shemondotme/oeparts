<?php

namespace Tests\Unit\Migration\Transport;

use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Transport\FetchHeaders;
use App\Services\Migration\Transport\HostResolver;
use App\Services\Migration\Transport\IpClassifier;
use App\Services\Migration\Transport\SafeHttpFetcher;
use App\Services\Migration\Transport\UrlPolicy;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

/**
 * The fetcher's own rules, against a mock transport: what it accepts into the file, what it refuses before a byte is
 * written, how every hop is checked, and what it sends. The real curl behaviour (pinning, IPv6, aborting on the headers) is
 * proven separately against a real TLS server in SafeHttpFetcherLoopbackTest.
 */
class SafeHttpFetcherTest extends TestCase
{
    /** @var list<array{request: RequestInterface, options: array<string, mixed>}> */
    private array $seen = [];

    /** @var array<string, int> */
    private array $lookups = [];

    /**
     * @param  list<Response|\Throwable>  $answers
     * @param  array<string, list<list<string>>>  $dns  host => the answer of the 1st lookup, the 2nd, ...
     */
    private function fetcher(array $answers, array $dns = []): SafeHttpFetcher
    {
        $dns += [
            'files.example.com' => [['93.184.216.34']],
            'cdn.example.com' => [['93.184.216.40', '2606:2800:220:1::1']],
            'v6.example.com' => [['2606:2800:220:1::1']],
            'evil.example.com' => [['10.0.0.7']],
        ];

        $resolver = new class($dns, $this->lookups) implements HostResolver
        {
            /** @param  array<string, list<list<string>>>  $dns */
            public function __construct(private readonly array $dns, private array &$lookups) {}

            public function resolve(string $host): array
            {
                $n = $this->lookups[$host] = ($this->lookups[$host] ?? 0) + 1;
                $answers = $this->dns[$host] ?? [[]];

                return $answers[min($n, count($answers)) - 1];
            }
        };

        $mock = new MockHandler($answers);
        $next = 0;
        $recording = function ($request, array $options) use ($mock, $answers, &$next) {
            $this->seen[] = ['request' => $request, 'options' => $options];

            // the mock would hand a thrown error to the on_headers callback as if it were a response; curl never does
            if (($answers[$next++] ?? null) instanceof \Throwable) {
                unset($options['on_headers']);
            }

            return $mock($request, $options);
        };

        return new SafeHttpFetcher(new UrlPolicy($resolver, new IpClassifier), $recording);
    }

    /** @return resource */
    private function sink()
    {
        return fopen('php://memory', 'w+');
    }

    private function contents($sink): string
    {
        rewind($sink);

        return (string) stream_get_contents($sink);
    }

    private function partial(string $body, int $start, int $total, array $headers = []): Response
    {
        return new Response(206, $headers + ['Content-Range' => 'bytes '.$start.'-'.($start + strlen($body) - 1).'/'.$total, 'Content-Length' => (string) strlen($body)], $body);
    }

    private function assertRefused(MigrationErrorCode $code, callable $do, ?string $reason = null): MigrationException
    {
        try {
            $do();
        } catch (MigrationException $e) {
            $this->assertSame($code, $e->errorCode, $e->getMessage());

            if ($reason !== null) {
                $this->assertSame($reason, $e->params['reason'] ?? null);
            }

            return $e;
        }

        $this->fail('Expected '.$code->value);
    }

    // ---- what is accepted -------------------------------------------------------------------------------------

    #[Test]
    public function a_range_that_starts_where_asked_is_written_and_the_claim_arrives_before_the_first_byte(): void
    {
        $fetcher = $this->fetcher([$this->partial('HELLOWORLD', 100, 5000, ['X-OeParts-Claim' => 'claim-secret'])]);
        $sink = $this->sink();
        $atCallback = null;

        $response = $fetcher->fetchRange('https://files.example.com/p.oeparts', $sink, 100, 10, ['on_headers' => function (FetchHeaders $h) use ($sink, &$atCallback): void {
            $atCallback = ['claim' => $h->header('X-OeParts-Claim'), 'written' => $this->contents($sink), 'total' => $h->total, 'start' => $h->rangeStart];
        }]);

        $this->assertSame(['claim' => 'claim-secret', 'written' => '', 'total' => 5000, 'start' => 100], $atCallback, 'the caller sees the claim while the file is still untouched');
        $this->assertSame('HELLOWORLD', $this->contents($sink));
        $this->assertSame(206, $response->status);
        $this->assertSame(10, $response->bytesWritten);
        $this->assertSame(5000, $response->total);
        $this->assertSame('claim-secret', $response->header('x-oeparts-claim'));
    }

    #[Test]
    public function a_whole_small_file_answered_with_200_is_accepted_at_offset_zero(): void
    {
        $fetcher = $this->fetcher([new Response(200, ['Content-Length' => '5'], 'small')]);
        $sink = $this->sink();

        $response = $fetcher->fetchRange('https://files.example.com/p.oeparts', $sink, 0, 1000);

        $this->assertSame('small', $this->contents($sink));
        $this->assertSame(5, $response->total);
    }

    #[Test]
    public function a_416_reports_the_real_size_and_writes_nothing(): void
    {
        $fetcher = $this->fetcher([new Response(416, ['Content-Range' => 'bytes */4096'], 'range not satisfiable page')]);
        $sink = $this->sink();

        $response = $fetcher->fetchRange('https://files.example.com/p.oeparts', $sink, 9999, 100);

        $this->assertTrue($response->isRangeNotSatisfiable());
        $this->assertSame(4096, $response->total);
        $this->assertSame('', $this->contents($sink));
    }

    // ---- what is refused before a byte is written ----------------------------------------------------------------

    #[Test]
    public function a_200_to_a_range_request_with_an_offset_is_refused_and_nothing_is_written(): void
    {
        $fetcher = $this->fetcher([new Response(200, ['Content-Length' => '10'], 'WHOLE-FILE')]);
        $sink = $this->sink();

        $this->assertRefused(MigrationErrorCode::UrlHttpError, fn () => $fetcher->fetchRange('https://files.example.com/p', $sink, 500, 100), 'range_ignored');
        $this->assertSame('', $this->contents($sink));
    }

    #[Test]
    public function a_200_whose_body_is_bigger_than_one_range_or_unsized_is_refused(): void
    {
        $sink = $this->sink();
        $big = $this->fetcher([new Response(200, ['Content-Length' => '5000'], str_repeat('x', 5000))]);
        $unsized = $this->fetcher([new Response(200, [], 'abc')]);

        $this->assertRefused(MigrationErrorCode::UrlHttpError, fn () => $big->fetchRange('https://files.example.com/p', $sink, 0, 100), 'range_ignored');
        $this->assertRefused(MigrationErrorCode::UrlHttpError, fn () => $unsized->fetchRange('https://files.example.com/p', $sink, 0, 100), 'range_ignored');
        $this->assertSame('', $this->contents($sink));
    }

    #[Test]
    public function a_content_range_that_starts_elsewhere_is_refused(): void
    {
        $fetcher = $this->fetcher([$this->partial('abcd', 0, 100)]);
        $sink = $this->sink();

        $this->assertRefused(MigrationErrorCode::UrlHttpError, fn () => $fetcher->fetchRange('https://files.example.com/p', $sink, 40, 4), 'range_mismatch');
        $this->assertSame('', $this->contents($sink));
    }

    #[Test]
    public function a_malformed_or_missing_content_range_is_refused(): void
    {
        foreach ([[], ['Content-Range' => 'items 0-3/100'], ['Content-Range' => 'bytes 0-3'], ['Content-Range' => 'bytes 3-0/100']] as $headers) {
            $fetcher = $this->fetcher([new Response(206, $headers, 'abcd')]);

            $this->assertRefused(MigrationErrorCode::UrlHttpError, fn () => $fetcher->fetchRange('https://files.example.com/p', $this->sink(), 0, 4), 'range_mismatch');
        }
    }

    #[Test]
    public function a_content_length_that_disagrees_with_the_content_range_is_refused(): void
    {
        $fetcher = $this->fetcher([new Response(206, ['Content-Range' => 'bytes 0-3/100', 'Content-Length' => '9'], 'abcd')]);

        $this->assertRefused(MigrationErrorCode::UrlHttpError, fn () => $fetcher->fetchRange('https://files.example.com/p', $this->sink(), 0, 4), 'range_mismatch');
    }

    #[Test]
    public function more_than_the_asked_range_is_refused_and_not_written(): void
    {
        $fetcher = $this->fetcher([$this->partial('0123456789', 0, 100)]);
        $sink = $this->sink();

        $this->assertRefused(MigrationErrorCode::UrlTooLarge, fn () => $fetcher->fetchRange('https://files.example.com/p', $sink, 0, 4));
        $this->assertSame('', $this->contents($sink));
    }

    #[Test]
    public function a_body_that_runs_past_what_the_headers_promised_is_stopped(): void
    {
        // headers promise 4 bytes, the body brings 10
        $fetcher = $this->fetcher([new Response(206, ['Content-Range' => 'bytes 0-3/100'], '0123456789')]);
        $sink = $this->sink();

        $this->assertRefused(MigrationErrorCode::UrlTooLarge, fn () => $fetcher->fetchRange('https://files.example.com/p', $sink, 0, 100));
        $this->assertSame('', $this->contents($sink), 'the first write past the promise is refused');
    }

    #[Test]
    public function a_content_encoding_is_refused(): void
    {
        $fetcher = $this->fetcher([$this->partial('abcd', 0, 100, ['Content-Encoding' => 'gzip'])]);

        $this->assertRefused(MigrationErrorCode::UrlHttpError, fn () => $fetcher->fetchRange('https://files.example.com/p', $this->sink(), 0, 4), 'encoded');
    }

    #[Test]
    public function an_error_page_is_never_written_into_the_file(): void
    {
        $sink = $this->sink();
        $fetcher = $this->fetcher([new Response(404, [], '<html>not found</html>')]);

        $e = $this->assertRefused(MigrationErrorCode::UrlHttpError, fn () => $fetcher->fetchRange('https://files.example.com/p', $sink, 0, 100));

        $this->assertSame(404, $e->params['status']);
        $this->assertSame('', $this->contents($sink));
        $this->assertFalse(SafeHttpFetcher::isRetryable($e));
    }

    #[Test]
    public function a_busy_server_is_retryable_and_its_retry_after_is_kept(): void
    {
        $fetcher = $this->fetcher([new Response(429, ['Retry-After' => '120'], 'slow down')]);

        $e = $this->assertRefused(MigrationErrorCode::UrlHttpError, fn () => $fetcher->fetchRange('https://files.example.com/p', $this->sink(), 0, 100));

        $this->assertSame(429, $e->params['status']);
        $this->assertSame(120, $e->params['retry_after']);
        $this->assertTrue(SafeHttpFetcher::isRetryable($e));
    }

    #[Test]
    public function a_body_cut_short_is_reported_so_the_caller_can_resume_from_what_is_on_disk(): void
    {
        $fetcher = $this->fetcher([new Response(206, ['Content-Range' => 'bytes 0-9/100', 'Content-Length' => '10'], 'only5')]);
        $sink = $this->sink();

        $e = $this->assertRefused(MigrationErrorCode::UrlHttpError, fn () => $fetcher->fetchRange('https://files.example.com/p', $sink, 0, 10), 'cut_short');

        $this->assertTrue(SafeHttpFetcher::isRetryable($e));
        $this->assertSame('only5', $this->contents($sink), 'the bytes that did arrive stay: resume starts at the real file size');
    }

    // ---- redirects: every hop is judged again ----------------------------------------------------------------------

    #[Test]
    public function a_redirect_to_a_public_https_host_is_followed_and_the_claim_stays_with_the_first_host(): void
    {
        $fetcher = $this->fetcher([
            new Response(302, ['Location' => 'https://cdn.example.com/blob/p.oeparts'], 'moved'),
            $this->partial('DATA', 0, 4),
        ]);
        $sink = $this->sink();

        $response = $fetcher->fetchRange('https://files.example.com/p', $sink, 0, 4, ['headers' => ['X-OeParts-Claim' => 'secret']]);

        $this->assertSame('DATA', $this->contents($sink), 'the redirect page was not written, only the real body');
        $this->assertSame(1, $response->redirects);
        $this->assertSame('https://cdn.example.com/blob/p.oeparts', $response->finalUrl);
        $this->assertSame('secret', $this->seen[0]['request']->getHeaderLine('X-OeParts-Claim'));
        $this->assertSame('', $this->seen[1]['request']->getHeaderLine('X-OeParts-Claim'), 'never sent to another host');
    }

    #[Test]
    public function a_relative_redirect_stays_on_the_same_host_and_keeps_the_claim(): void
    {
        $fetcher = $this->fetcher([new Response(307, ['Location' => '/other/path'], ''), $this->partial('DATA', 0, 4)]);

        $fetcher->fetchRange('https://files.example.com/p', $this->sink(), 0, 4, ['headers' => ['X-OeParts-Claim' => 'secret']]);

        $this->assertSame('https://files.example.com/other/path', (string) $this->seen[1]['request']->getUri());
        $this->assertSame('secret', $this->seen[1]['request']->getHeaderLine('X-OeParts-Claim'));
    }

    #[Test]
    public function a_redirect_to_http_or_to_a_private_place_is_refused_as_a_blocked_redirect(): void
    {
        foreach ([
            'http://files.example.com/p' => 'https',
            'https://evil.example.com/p' => 'private network',
            'https://169.254.169.254/latest/meta-data' => 'link-local',
            'https://127.0.0.1/p' => 'loopback',
            'https://2130706433/p' => 'numeric',
            'https://files.example.com:8080/p' => 'port',
            'https://user:pw@files.example.com/p' => 'user name',
        ] as $location => $why) {
            $fetcher = $this->fetcher([new Response(302, ['Location' => $location], '')]);
            $e = $this->assertRefused(MigrationErrorCode::UrlRedirectBlocked, fn () => $fetcher->fetchRange('https://files.example.com/p', $this->sink(), 0, 4));

            $this->assertCount(1, $this->seen, "{$location}: no second request was made");
            $this->seen = [];
        }
    }

    #[Test]
    public function more_than_three_redirects_stop_the_fetch(): void
    {
        $hops = array_fill(0, 4, new Response(302, ['Location' => 'https://files.example.com/next'], ''));
        $fetcher = $this->fetcher($hops);

        $this->assertRefused(MigrationErrorCode::UrlRedirectBlocked, fn () => $fetcher->fetchRange('https://files.example.com/p', $this->sink(), 0, 4), 'too many redirects');
        $this->assertCount(4, $this->seen);
    }

    #[Test]
    public function exactly_three_redirects_are_allowed(): void
    {
        $answers = [...array_fill(0, 3, new Response(302, ['Location' => 'https://files.example.com/next'], '')), $this->partial('DATA', 0, 4)];

        $response = $this->fetcher($answers)->fetchRange('https://files.example.com/p', $this->sink(), 0, 4);

        $this->assertSame(3, $response->redirects);
    }

    #[Test]
    public function a_redirect_without_a_location_is_refused(): void
    {
        $fetcher = $this->fetcher([new Response(302, [], '')]);

        $this->assertRefused(MigrationErrorCode::UrlRedirectBlocked, fn () => $fetcher->fetchRange('https://files.example.com/p', $this->sink(), 0, 4), 'redirect without a location');
    }

    // ---- what is sent ------------------------------------------------------------------------------------------------

    #[Test]
    public function the_request_is_pinned_https_only_proxyless_identity_encoded_and_bounded(): void
    {
        $fetcher = $this->fetcher([$this->partial('abcd', 8, 100)]);

        $fetcher->fetchRange('https://files.example.com/p?x=1', $this->sink(), 8, 4, ['headers' => ['Accept-Encoding' => 'gzip', 'Range' => 'bytes=0-999999999']]);

        $options = $this->seen[0]['options'];
        $request = $this->seen[0]['request'];

        $this->assertSame('bytes=8-11', $request->getHeaderLine('Range'), 'a caller header cannot widen the range');
        $this->assertSame('identity', $request->getHeaderLine('Accept-Encoding'));
        $this->assertSame('https://files.example.com/p?x=1', (string) $request->getUri());
        $this->assertSame(['files.example.com:443:93.184.216.34'], $options['curl'][CURLOPT_RESOLVE]);
        $this->assertSame(['https'], $options['protocols']);
        $this->assertSame('', $options['proxy']);
        $this->assertFalse($options['allow_redirects']);
        $this->assertTrue($options['verify']);
        $this->assertFalse($options['decode_content']);
        $this->assertGreaterThan(0, $options['curl'][CURLOPT_LOW_SPEED_LIMIT]);
    }

    #[Test]
    public function an_ipv6_only_host_is_pinned_in_brackets(): void
    {
        $this->fetcher([$this->partial('abcd', 0, 4)])->fetchRange('https://v6.example.com/p', $this->sink(), 0, 4);

        $this->assertSame(['v6.example.com:443:[2606:2800:220:1::1]'], $this->seen[0]['options']['curl'][CURLOPT_RESOLVE]);
    }

    #[Test]
    public function the_name_is_resolved_once_per_hop_and_the_connection_uses_that_first_answer(): void
    {
        // a rebinding server: public on the first lookup, loopback on every later one
        $fetcher = $this->fetcher([$this->partial('abcd', 0, 4)], ['rebind.example.com' => [['93.184.216.34'], ['127.0.0.1']]]);

        $fetcher->fetchRange('https://rebind.example.com/p', $this->sink(), 0, 4);

        $this->assertSame(1, $this->lookups['rebind.example.com']);
        $this->assertSame(['rebind.example.com:443:93.184.216.34'], $this->seen[0]['options']['curl'][CURLOPT_RESOLVE]);
    }

    #[Test]
    public function the_first_url_is_checked_before_anything_is_sent(): void
    {
        $fetcher = $this->fetcher([]);

        foreach (['http://files.example.com/p' => MigrationErrorCode::UrlNotHttps, 'https://evil.example.com/p' => MigrationErrorCode::UrlBlocked, 'https://127.0.0.1/p' => MigrationErrorCode::UrlBlocked] as $url => $code) {
            $this->assertRefused($code, fn () => $fetcher->fetchRange($url, $this->sink(), 0, 4));
        }

        $this->assertSame([], $this->seen);
    }

    // ---- transport failures in the catalogue's words ------------------------------------------------------------

    #[Test]
    public function transport_failures_become_catalogue_errors(): void
    {
        $cases = [
            [28, MigrationErrorCode::UrlTimeout, true],
            [63, MigrationErrorCode::UrlTooLarge, false],
            [60, MigrationErrorCode::UrlHttpError, false],
            [35, MigrationErrorCode::UrlHttpError, false],
            [18, MigrationErrorCode::UrlHttpError, true],
            [7, MigrationErrorCode::UrlHttpError, true],
            [56, MigrationErrorCode::UrlHttpError, true],
        ];

        foreach ($cases as [$errno, $code, $retryable]) {
            $failure = new ConnectException('curl error', new Request('GET', 'https://files.example.com/p'), null, ['errno' => $errno]);
            $e = $this->assertRefused($code, fn () => $this->fetcher([$failure])->fetchRange('https://files.example.com/p', $this->sink(), 0, 4));

            $this->assertSame($retryable, SafeHttpFetcher::isRetryable($e), "errno {$errno}");
        }
    }

    #[Test]
    public function a_certificate_failure_is_named_tls_and_is_not_retried(): void
    {
        $failure = new ConnectException('bad cert', new Request('GET', 'https://files.example.com/p'), null, ['errno' => 60]);

        $this->assertRefused(MigrationErrorCode::UrlHttpError, fn () => $this->fetcher([$failure])->fetchRange('https://files.example.com/p', $this->sink(), 0, 4), 'tls');
    }
}
