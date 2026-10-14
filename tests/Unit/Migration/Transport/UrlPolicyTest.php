<?php

namespace Tests\Unit\Migration\Transport;

use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Transport\HostResolver;
use App\Services\Migration\Transport\IpClassifier;
use App\Services\Migration\Transport\UrlPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The URL tricks and the DNS answers a hostile link can bring. No network: the resolver is a fake. */
class UrlPolicyTest extends TestCase
{
    /** @param  array<string, list<string>>  $dns */
    private function policy(array $dns = []): UrlPolicy
    {
        $resolver = new class($dns) implements HostResolver
        {
            /** @var array<string, int> */
            public array $calls = [];

            /** @param  array<string, list<string>>  $answers */
            public function __construct(private array $answers) {}

            public function resolve(string $host): array
            {
                $this->calls[$host] = ($this->calls[$host] ?? 0) + 1;

                return $this->answers[$host] ?? [];
            }
        };

        return new UrlPolicy($resolver, new IpClassifier);
    }

    private function assertBlocked(string $url, array $dns = [], ?string $reasonPart = null): void
    {
        try {
            $this->policy($dns)->validate($url);
            $this->fail("Expected the URL to be refused: {$url}");
        } catch (MigrationException $e) {
            $this->assertContains($e->errorCode, [MigrationErrorCode::UrlBlocked, MigrationErrorCode::UrlNotHttps], $url);

            if ($reasonPart !== null) {
                $this->assertStringContainsString($reasonPart, (string) ($e->params['reason'] ?? ''), $url);
            }
        }
    }

    /** @return array<string, array{string}> */
    public static function trickyUrls(): array
    {
        $cases = [
            'http scheme' => 'http://example.com/a.oeparts',
            'ftp scheme' => 'ftp://example.com/a.oeparts',
            'file scheme' => 'file:///etc/passwd',
            'no scheme' => 'example.com/a.oeparts',
            'scheme-relative' => '//example.com/a.oeparts',
            'gopher' => 'gopher://example.com/',
            'empty' => '',
            'userinfo' => 'https://user:pass@example.com/a.oeparts',
            'userinfo trick' => 'https://example.com@127.0.0.1/a.oeparts',
            'user only' => 'https://admin@example.com/a.oeparts',
            'decimal ip' => 'https://2130706433/a.oeparts',
            'hex ip' => 'https://0x7f000001/a.oeparts',
            'hex dotted' => 'https://0x7f.0x0.0x0.0x1/a.oeparts',
            'octal ip' => 'https://0177.0.0.1/a.oeparts',
            'short ip' => 'https://127.1/a.oeparts',
            'mixed ip' => 'https://127.0.1/a.oeparts',
            'loopback literal' => 'https://127.0.0.1/a.oeparts',
            'metadata literal' => 'https://169.254.169.254/latest/meta-data/',
            'private literal' => 'https://10.1.2.3/a.oeparts',
            'ipv6 loopback' => 'https://[::1]/a.oeparts',
            'ipv6 mapped' => 'https://[::ffff:127.0.0.1]/a.oeparts',
            'ipv6 metadata' => 'https://[fd00:ec2::254]/a.oeparts',
            'ipv6 broken' => 'https://[::1/a.oeparts',
            'ipv6 zone' => 'https://[fe80::1%25eth0]/a.oeparts',
            'trailing dot' => 'https://example.com./a.oeparts',
            'leading dot' => 'https://.example.com/a.oeparts',
            'double dot' => 'https://example..com/a.oeparts',
            'localhost' => 'https://localhost/a.oeparts',
            'localhost sub' => 'https://api.localhost/a.oeparts',
            'dot local' => 'https://printer.local/a.oeparts',
            'dot internal' => 'https://db.internal/a.oeparts',
            'dot lan' => 'https://nas.lan/a.oeparts',
            'home arpa' => 'https://router.home.arpa/a.oeparts',
            'single label' => 'https://intranet/a.oeparts',
            'space' => 'https://exa mple.com/a.oeparts',
            'newline' => "https://example.com/a\n.oeparts",
            'tab' => "https://example.com/a\t.oeparts",
            'nul' => "https://example.com/a\0.oeparts",
            'backslash' => 'https://example.com\\@127.0.0.1/a.oeparts',
            'underscore host' => 'https://my_host.example.com/a.oeparts',
            'port 8080' => 'https://example.com:8080/a.oeparts',
            'port 22' => 'https://example.com:22/a.oeparts',
            'port 80' => 'https://example.com:80/a.oeparts',
            'no host' => 'https:///a.oeparts',
            'long' => 'https://example.com/'.str_repeat('a', 2100),
            'hyphen edge' => 'https://-example.com/a.oeparts',
        ];

        return array_map(static fn (string $url): array => [$url], $cases);
    }

    #[Test]
    #[DataProvider('trickyUrls')]
    public function a_tricky_url_is_refused(string $url): void
    {
        $this->assertBlocked($url, ['example.com' => ['93.184.216.34']]);
    }

    #[Test]
    public function http_is_refused_with_the_https_code(): void
    {
        try {
            $this->policy(['example.com' => ['93.184.216.34']])->validate('http://example.com/a.oeparts');
            $this->fail('expected refusal');
        } catch (MigrationException $e) {
            $this->assertSame(MigrationErrorCode::UrlNotHttps, $e->errorCode);
        }
    }

    #[Test]
    public function a_good_url_is_canonicalised_and_the_first_public_ipv4_is_pinned(): void
    {
        $result = $this->policy(['example.com' => ['2606:2800:220:1::1', '93.184.216.34', '93.184.216.35']])
            ->validate(' HTTPS://Example.COM/pkg/a.oeparts?x=1#frag ');

        $this->assertSame('https://example.com/pkg/a.oeparts?x=1', $result->url);
        $this->assertSame('example.com', $result->host);
        $this->assertSame(443, $result->port);
        $this->assertSame('/pkg/a.oeparts?x=1', $result->pathAndQuery);
        $this->assertSame('93.184.216.34', $result->pinnedAddress);
        $this->assertCount(3, $result->addresses);
    }

    #[Test]
    public function an_address_that_is_only_ipv6_is_pinned_as_ipv6(): void
    {
        $result = $this->policy(['v6.example.com' => ['2606:2800:220:1::1']])->validate('https://v6.example.com/');

        $this->assertSame('2606:2800:220:1::1', $result->pinnedAddress);
        $this->assertSame('/', $result->pathAndQuery);
    }

    #[Test]
    public function a_public_ip_literal_is_accepted_in_canonical_form(): void
    {
        $this->assertSame('1.1.1.1', $this->policy()->validate('https://1.1.1.1/a.oeparts')->pinnedAddress);
        $this->assertSame('https://[2606:4700:4700::1111]/a', $this->policy()->validate('https://[2606:4700:4700::1111]/a')->url);
    }

    #[Test]
    public function one_private_answer_among_public_ones_refuses_the_whole_name(): void
    {
        $this->assertBlocked('https://mixed.example.com/a', ['mixed.example.com' => ['93.184.216.34', '10.0.0.5']], 'private network');
    }

    #[Test]
    public function a_public_ipv4_with_a_private_ipv6_is_refused(): void
    {
        $this->assertBlocked('https://dual.example.com/a', ['dual.example.com' => ['93.184.216.34', '::1']], 'loopback');
        $this->assertBlocked('https://dual2.example.com/a', ['dual2.example.com' => ['93.184.216.34', '::ffff:127.0.0.1']], 'loopback');
    }

    #[Test]
    public function an_ipv6_only_private_answer_is_refused(): void
    {
        $this->assertBlocked('https://aaaa.example.com/a', ['aaaa.example.com' => ['fd00::1']], 'unique local');
    }

    #[Test]
    public function a_name_that_does_not_resolve_is_refused(): void
    {
        $this->assertBlocked('https://nothing.example.com/a', [], 'does not resolve');
    }

    #[Test]
    public function the_name_is_resolved_exactly_once(): void
    {
        $dns = ['once.example.com' => ['93.184.216.34']];
        $policy = $this->policy($dns);
        $policy->validate('https://once.example.com/a');

        $resolver = (new \ReflectionProperty($policy, 'resolver'))->getValue($policy);
        $this->assertSame(1, $resolver->calls['once.example.com']);
    }

    #[Test]
    public function an_international_name_is_converted_to_ascii(): void
    {
        $result = $this->policy(['xn--bcher-kva.example' => ['93.184.216.34']])->validate('https://bücher.example/a');

        $this->assertSame('xn--bcher-kva.example', $result->host);
    }

    #[Test]
    public function the_allowed_list_matches_at_a_label_boundary(): void
    {
        config(['migration.transport.allowed_hosts' => ['example.com']]);
        $dns = ['example.com' => ['93.184.216.34'], 'files.example.com' => ['93.184.216.34'], 'evilexample.com' => ['93.184.216.34'], 'example.com.evil.net' => ['93.184.216.34']];

        $this->assertSame('example.com', $this->policy($dns)->validate('https://example.com/a')->host);
        $this->assertSame('files.example.com', $this->policy($dns)->validate('https://files.example.com/a')->host);
        $this->assertBlocked('https://evilexample.com/a', $dns, 'allowed list');
        $this->assertBlocked('https://example.com.evil.net/a', $dns, 'allowed list');
    }

    #[Test]
    public function an_owner_listed_port_is_accepted(): void
    {
        config(['migration.transport.allowed_ports' => [443, 8443]]);

        $result = $this->policy(['example.com' => ['93.184.216.34']])->validate('https://example.com:8443/a');

        $this->assertSame(8443, $result->port);
        $this->assertSame('https://example.com:8443/a', $result->url);
    }

    #[Test]
    public function the_private_hosts_switch_relaxes_the_address_check_but_never_https_or_the_url_tricks(): void
    {
        config(['migration.transport.allow_private_hosts' => true]);
        $policy = $this->policy(['source' => ['172.18.0.5'], 'db.internal' => ['10.0.0.9']]);

        $this->assertSame('172.18.0.5', $policy->validate('https://source/a')->pinnedAddress);
        $this->assertSame('10.0.0.9', $policy->validate('https://db.internal/a')->pinnedAddress);
        $this->assertSame('127.0.0.1', $policy->validate('https://127.0.0.1/a')->pinnedAddress);

        $this->assertBlocked('http://source/a');
        $this->assertBlocked('https://2130706433/a');
        $this->assertBlocked('https://user@source/a', ['source' => ['172.18.0.5']]);
    }

    #[Test]
    public function the_switch_is_a_config_value_read_from_the_environment_never_from_the_database(): void
    {
        $this->assertFalse((bool) config('migration.transport.allow_private_hosts'));
        $this->assertStringContainsString("env('OE_MIGRATION_ALLOW_PRIVATE_HOSTS'", file_get_contents(base_path('config/migration.php')));
    }

    #[Test]
    public function plain_http_is_accepted_only_when_the_caller_opts_in_and_then_only_on_port_80_or_443(): void
    {
        $policy = $this->policy(['img.example.com' => ['93.184.216.34']]);

        $this->assertBlocked('http://img.example.com/a.png', ['img.example.com' => ['93.184.216.34']]);

        $result = $policy->validate('http://img.example.com/a.png', allowHttp: true);
        $this->assertSame('http://img.example.com/a.png', $result->url);
        $this->assertSame(80, $result->port);
        $this->assertSame('http', $result->scheme);
        $this->assertSame('img.example.com:80:93.184.216.34', $result->resolveEntry());

        $this->assertSame('https://img.example.com/a.png', $policy->validate('https://img.example.com/a.png', allowHttp: true)->url);

        foreach (['http://img.example.com:8080/a.png', 'ftp://img.example.com/a.png', 'http://127.0.0.1/a.png', 'http://user@img.example.com/a.png'] as $bad) {
            try {
                $policy->validate($bad, allowHttp: true);
                $this->fail("expected refusal of {$bad}");
            } catch (MigrationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function a_refusal_says_whether_the_address_the_name_or_the_shape_was_the_problem(): void
    {
        $kind = function (string $url, array $dns = []): string {
            try {
                $this->policy($dns)->validate($url);
            } catch (MigrationException $e) {
                return (string) ($e->params['kind'] ?? '');
            }

            return 'none';
        };

        $this->assertSame('address', $kind('https://a.example.com/x', ['a.example.com' => ['10.0.0.1']]));
        $this->assertSame('address', $kind('https://127.0.0.1/x'));
        $this->assertSame('unresolvable', $kind('https://gone.example.com/x'));
        $this->assertSame('format', $kind('https://2130706433/x'));
    }

    #[Test]
    public function an_ipv6_pin_is_written_in_brackets(): void
    {
        $this->assertSame('v6.example.com:443:[2606:2800:220:1::1]', $this->policy(['v6.example.com' => ['2606:2800:220:1::1']])->validate('https://v6.example.com/')->resolveEntry());
    }
}
