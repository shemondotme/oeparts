<?php

namespace Tests\Unit\Migration\Transport;

use App\Services\Migration\Transport\IpClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Every range the classifier names, both edges, and the forms that hide an IPv4 address inside an IPv6 one. */
class IpClassifierTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function refused(): array
    {
        $cases = [
            '0.0.0.0', '0.255.255.255', '10.0.0.1', '10.255.255.255', '100.64.0.1', '100.127.255.255', '127.0.0.1', '127.255.255.254',
            '169.254.169.254', '169.254.0.1', '172.16.0.1', '172.31.255.255', '192.0.0.8', '192.0.2.1', '192.88.99.1', '192.168.0.1',
            '192.168.255.255', '198.18.0.1', '198.19.255.255', '198.51.100.7', '203.0.113.9', '224.0.0.1', '239.255.255.255',
            '240.0.0.1', '255.255.255.255', '168.63.129.16',
            '::', '::1', '::2', '100::1', '2001::1', '2001:0:4136:e378:8000:63bf:3fff:fdd2', '2001:db8::1', '2002:7f00:1::1', '2002::',
            'fc00::1', 'fd00:ec2::254', 'fdff:ffff::1', 'fe80::1', 'fe80::1ff:fe23:4567:890a', 'fec0::1', 'ff02::1', 'ff0e::1',
            '64:ff9b:1::1', '3fff::1', '2001:10::1', '2001:20::1',
            // an IPv4 address inside an IPv6 one is judged as the IPv4 address
            '::ffff:127.0.0.1', '::ffff:7f00:1', '::ffff:10.0.0.1', '::ffff:169.254.169.254', '64:ff9b::7f00:1', '64:ff9b::169.254.169.254',
            '::127.0.0.1',
        ];

        return array_combine($cases, array_map(static fn (string $ip): array => [$ip], $cases));
    }

    /** @return array<string, array{string}> */
    public static function allowed(): array
    {
        $cases = [
            '1.1.1.1', '8.8.8.8', '93.184.216.34', '100.63.255.255', '100.128.0.1', '169.253.255.255', '169.255.0.1', '172.15.255.255',
            '172.32.0.1', '192.0.1.1', '192.167.255.255', '192.169.0.1', '198.17.255.255', '198.20.0.1', '223.255.255.255',
            '2606:4700:4700::1111', '2001:4860:4860::8888', '2a00:1450:4001:81b::200e', '2001:1::1',
            '::ffff:8.8.8.8', '64:ff9b::808:808',
        ];

        return array_combine($cases, array_map(static fn (string $ip): array => [$ip], $cases));
    }

    #[Test]
    #[DataProvider('refused')]
    public function a_non_public_address_is_refused_with_a_reason(string $ip): void
    {
        $classifier = new IpClassifier;

        $this->assertFalse($classifier->isPublic($ip), $ip);
        $this->assertIsString($classifier->blockedReason($ip));
    }

    #[Test]
    #[DataProvider('allowed')]
    public function a_public_address_is_allowed(string $ip): void
    {
        $this->assertTrue((new IpClassifier)->isPublic($ip), $ip);
    }

    #[Test]
    public function something_that_is_not_an_address_is_refused(): void
    {
        foreach (['', 'example.com', '1.2.3', '999.1.1.1', '1.2.3.4.5', '[::1]', '2130706433', '0x7f000001', '::g'] as $text) {
            $this->assertFalse((new IpClassifier)->isPublic($text), $text);
        }
    }

    #[Test]
    public function the_servers_own_address_is_refused_even_when_it_looks_public(): void
    {
        $classifier = new IpClassifier(['93.184.216.34', '2606:4700:4700::1111']);

        $this->assertSame('this server', $classifier->blockedReason('93.184.216.34'));
        $this->assertSame('this server', $classifier->blockedReason('::ffff:93.184.216.34'));
        $this->assertSame('this server', $classifier->blockedReason('2606:4700:4700::1111'));
        $this->assertTrue($classifier->isPublic('1.1.1.1'));
    }
}
