<?php

namespace Tests\Feature\Migration;

use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Transport\FetchHeaders;
use App\Services\Migration\Transport\HostResolver;
use App\Services\Migration\Transport\IpClassifier;
use App\Services\Migration\Transport\SafeHttpFetcher;
use App\Services\Migration\Transport\UrlPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The behaviour of REAL curl that a mock cannot prove, against a real TLS server on this machine: the connection goes to the
 * pinned address while the certificate and the Host header still name the host; the pin works in the IPv6 bracket form; a 200
 * to a range request is stopped on its headers (before the body); a cut body is reported; a redirect to http is refused.
 * The server is a child PHP process serving a self-signed certificate that the fetcher is told to trust.
 */
class SafeHttpFetcherLoopbackTest extends TestCase
{
    private const HOST = 'pkg.test';

    /** @var array<string, array{process: resource, port: int}> */
    private static array $servers = [];

    private static string $dir = '';

    private static string $pem = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oeparts-loopback-'.bin2hex(random_bytes(4));
        mkdir(self::$dir);
        self::$pem = self::makeCertificate();
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$servers as $server) {
            @proc_terminate($server['process']);
            @proc_close($server['process']);
        }

        self::$servers = [];

        foreach (glob(self::$dir.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir(self::$dir);

        parent::tearDownAfterClass();
    }

    private static function makeCertificate(): string
    {
        $config = self::$dir.'/openssl.cnf';
        file_put_contents($config, "[req]\ndistinguished_name=dn\nx509_extensions=v3\nprompt=no\n[dn]\nCN=".self::HOST."\n[v3]\nsubjectAltName=DNS:".self::HOST.",IP:127.0.0.1,IP:::1\nbasicConstraints=CA:FALSE\n");

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'config' => $config]);
        $csr = openssl_csr_new(['commonName' => self::HOST], $key, ['config' => $config]);
        $cert = openssl_csr_sign($csr, null, $key, 2, ['config' => $config, 'x509_extensions' => 'v3']);

        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($key, $keyPem, null, ['config' => $config]);

        $pem = self::$dir.'/server.pem';
        file_put_contents($pem, $certPem.$keyPem);
        file_put_contents(self::$dir.'/ca.pem', $certPem);

        return $pem;
    }

    /** Start (once) the server on an address; skip the test when this machine cannot do it. */
    private function server(string $address): int
    {
        if (isset(self::$servers[$address])) {
            return self::$servers[$address]['port'];
        }

        if (! function_exists('proc_open') || ! extension_loaded('openssl')) {
            $this->markTestSkipped('proc_open and openssl are needed for the loopback server.');
        }

        $probe = @stream_socket_server('tcp://'.(str_contains($address, ':') ? '['.$address.']' : $address).':0', $errno, $errstr);

        if ($probe === false) {
            $this->markTestSkipped("This machine cannot listen on {$address}: {$errstr}");
        }

        $port = (int) substr((string) stream_socket_get_name($probe, false), (int) strrpos((string) stream_socket_get_name($probe, false), ':') + 1);
        fclose($probe);

        $process = proc_open([PHP_BINARY, base_path('tests/Fixtures/https_range_server.php'), $address, (string) $port, self::$pem], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (! is_resource($process)) {
            $this->markTestSkipped('Could not start the loopback server.');
        }

        for ($i = 0; $i < 100; $i++) {
            $conn = @stream_socket_client('tcp://'.(str_contains($address, ':') ? '['.$address.']' : $address).':'.$port, $e, $s, 0.2);

            if ($conn !== false) {
                fclose($conn);
                self::$servers[$address] = ['process' => $process, 'port' => $port];

                return $port;
            }

            usleep(100_000);
        }

        proc_terminate($process);
        $this->markTestSkipped('The loopback server did not start.');
    }

    private function fetcher(string $address, int $port): SafeHttpFetcher
    {
        config(['migration.transport.allow_private_hosts' => true, 'migration.transport.allowed_ports' => [$port]]);

        $resolver = new class($address) implements HostResolver
        {
            public function __construct(private readonly string $address) {}

            public function resolve(string $host): array
            {
                // the name points NOWHERE in real DNS: only the pin can make the connection work
                return $host === 'pkg.test' ? [$this->address] : [];
            }
        };

        return new SafeHttpFetcher(new UrlPolicy($resolver, new IpClassifier));
    }

    private function fetch(SafeHttpFetcher $fetcher, int $port, string $path, int $offset, int $length, array $options = []): array
    {
        $sink = fopen('php://memory', 'w+');
        $response = $fetcher->fetchRange('https://'.self::HOST.':'.$port.$path, $sink, $offset, $length, ['verify' => self::$dir.'/ca.pem'] + $options);
        rewind($sink);

        return [$response, (string) stream_get_contents($sink)];
    }

    /** @return array<string, array{string}> */
    public static function addresses(): array
    {
        return ['IPv4 loopback' => ['127.0.0.1'], 'IPv6 loopback' => ['::1']];
    }

    #[Test]
    #[DataProvider('addresses')]
    public function a_range_is_fetched_through_the_pin_with_the_certificate_and_host_still_naming_the_host(string $address): void
    {
        $port = $this->server($address);
        $seen = null;

        [$response, $body] = $this->fetch($this->fetcher($address, $port), $port, '/file', 100, 50, ['headers' => ['X-Client-Claim' => 'abc'], 'on_headers' => function (FetchHeaders $h) use (&$seen): void {
            $seen = $h->header('x-oeparts-claim');
        }]);

        $this->assertSame(substr(str_repeat('0123456789', 100), 100, 50), $body);
        $this->assertSame(206, $response->status);
        $this->assertSame(1000, $response->total);
        $this->assertSame('real-claim', $seen);
    }

    #[Test]
    public function the_certificate_is_still_checked_against_the_name_when_the_pin_is_used(): void
    {
        $port = $this->server('127.0.0.1');
        $fetcher = $this->fetcher('127.0.0.1', $port);

        // no trusted CA given: the self-signed certificate must be refused
        $sink = fopen('php://memory', 'w+');

        try {
            $fetcher->fetchRange('https://'.self::HOST.':'.$port.'/file', $sink, 0, 10);
            $this->fail('the unknown certificate should have been refused');
        } catch (MigrationException $e) {
            $this->assertSame(MigrationErrorCode::UrlHttpError, $e->errorCode);
            $this->assertSame('tls', $e->params['reason']);
        }

        rewind($sink);
        $this->assertSame('', stream_get_contents($sink));
    }

    #[Test]
    public function a_200_to_a_range_request_is_stopped_on_its_headers_before_the_body_is_written(): void
    {
        $port = $this->server('127.0.0.1');
        $started = microtime(true);

        try {
            $this->fetch($this->fetcher('127.0.0.1', $port), $port, '/ignore-range', 100, 50);
            $this->fail('expected refusal');
        } catch (MigrationException $e) {
            $this->assertSame('range_ignored', $e->params['reason']);
        }

        $this->assertLessThan(5.0, microtime(true) - $started, 'the 5 MB body was never read');
    }

    #[Test]
    public function a_416_gives_the_real_size(): void
    {
        $port = $this->server('127.0.0.1');

        [$response, $body] = $this->fetch($this->fetcher('127.0.0.1', $port), $port, '/file', 5000, 100);

        $this->assertTrue($response->isRangeNotSatisfiable());
        $this->assertSame(1000, $response->total);
        $this->assertSame('', $body);
    }

    #[Test]
    public function a_body_cut_short_keeps_what_arrived_and_is_retryable(): void
    {
        $port = $this->server('127.0.0.1');
        $sink = fopen('php://memory', 'w+');

        try {
            $this->fetcher('127.0.0.1', $port)->fetchRange('https://'.self::HOST.':'.$port.'/cut', $sink, 0, 10, ['verify' => self::$dir.'/ca.pem']);
            $this->fail('expected a cut-short error');
        } catch (MigrationException $e) {
            $this->assertSame('cut_short', $e->params['reason']);
            $this->assertTrue(SafeHttpFetcher::isRetryable($e));
        }

        rewind($sink);
        $this->assertSame('abcd', stream_get_contents($sink));
    }

    #[Test]
    public function a_redirect_on_the_same_host_is_followed_and_one_to_http_is_refused(): void
    {
        $port = $this->server('127.0.0.1');
        $fetcher = $this->fetcher('127.0.0.1', $port);

        [$response, $body] = $this->fetch($fetcher, $port, '/redirect', 0, 10);
        $this->assertSame('0123456789', $body);
        $this->assertSame(1, $response->redirects);

        try {
            $this->fetch($fetcher, $port, '/redirect-http', 0, 10);
            $this->fail('expected a blocked redirect');
        } catch (MigrationException $e) {
            $this->assertSame(MigrationErrorCode::UrlRedirectBlocked, $e->errorCode);
        }
    }

    #[Test]
    public function a_content_encoded_answer_is_refused(): void
    {
        $port = $this->server('127.0.0.1');

        try {
            $this->fetch($this->fetcher('127.0.0.1', $port), $port, '/gzip', 0, 4);
            $this->fail('expected refusal');
        } catch (MigrationException $e) {
            $this->assertSame('encoded', $e->params['reason']);
        }
    }

    #[Test]
    public function an_error_status_is_reported_with_its_code(): void
    {
        $port = $this->server('127.0.0.1');

        try {
            $this->fetch($this->fetcher('127.0.0.1', $port), $port, '/missing', 0, 4);
            $this->fail('expected an error');
        } catch (MigrationException $e) {
            $this->assertSame(404, $e->params['status'] ?? null, $e->getMessage());
            $this->assertFalse(SafeHttpFetcher::isRetryable($e));
        }
    }
}
