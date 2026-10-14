<?php

namespace App\Services\Migration\Transport;

/**
 * Says whether an address is a PUBLIC internet address. Everything else (loopback, private, link-local, cloud metadata,
 * carrier-grade NAT, documentation and test ranges, multicast, reserved, and the IPv6 tunnelling forms that can hide an
 * IPv4 address) is refused: this is the one place that decides what the server may be made to call.
 *
 * IPv6 is allowed only inside 2000::/3 (global unicast) and outside the listed exceptions; an IPv4-mapped (::ffff:a.b.c.d)
 * or NAT64 (64:ff9b::/96) address is judged by the IPv4 address inside it.
 */
final class IpClassifier
{
    /** @var array<string, string> CIDR => label */
    private const BLOCKED_V4 = [
        '0.0.0.0/8' => 'this network',
        '10.0.0.0/8' => 'private network',
        '100.64.0.0/10' => 'carrier-grade NAT',
        '127.0.0.0/8' => 'loopback',
        '169.254.0.0/16' => 'link-local (cloud metadata)',
        '172.16.0.0/12' => 'private network',
        '192.0.0.0/24' => 'protocol assignments',
        '192.0.2.0/24' => 'documentation',
        '192.88.99.0/24' => '6to4 relay',
        '192.168.0.0/16' => 'private network',
        '198.18.0.0/15' => 'benchmarking',
        '198.51.100.0/24' => 'documentation',
        '203.0.113.0/24' => 'documentation',
        '224.0.0.0/4' => 'multicast',
        '240.0.0.0/4' => 'reserved',
        '168.63.129.16/32' => 'cloud platform address',
    ];

    /** @var array<string, string> */
    private const BLOCKED_V6 = [
        '::/128' => 'unspecified',
        '::1/128' => 'loopback',
        '::/96' => 'IPv4-compatible',
        '100::/64' => 'discard-only',
        '2001::/32' => 'Teredo tunnel',
        '2001:10::/28' => 'ORCHID',
        '2001:20::/28' => 'ORCHIDv2',
        '2001:db8::/32' => 'documentation',
        '2002::/16' => '6to4 tunnel',
        '3fff::/20' => 'documentation',
        '64:ff9b:1::/48' => 'local-use NAT64',
        'fc00::/7' => 'unique local',
        'fe80::/10' => 'link-local',
        'fec0::/10' => 'site-local',
        'ff00::/8' => 'multicast',
    ];

    /** @var list<string> */
    private array $own = [];

    /** @param  list<string>  $ownAddresses The server's own addresses: calling oneself is refused too. */
    public function __construct(array $ownAddresses = [])
    {
        foreach ($ownAddresses as $address) {
            $binary = @inet_pton($address);

            if ($binary !== false) {
                $this->own[] = $binary;
            }
        }
    }

    public function isPublic(string $ip): bool
    {
        return $this->blockedReason($ip) === null;
    }

    /** Null when the address is public, otherwise the plain name of the range that refuses it. */
    public function blockedReason(string $ip): ?string
    {
        $binary = @inet_pton($ip);

        if ($binary === false) {
            return 'not an IP address';
        }

        if (strlen($binary) === 16) {
            $inner = $this->embeddedV4($binary);

            if ($inner !== null) {
                return $this->blockedReason($inner);
            }
        }

        if (in_array($binary, $this->own, true)) {
            return 'this server';
        }

        if (strlen($binary) === 4) {
            return $this->matchAny($binary, self::BLOCKED_V4);
        }

        $named = $this->matchAny($binary, self::BLOCKED_V6);

        if ($named !== null) {
            return $named;
        }

        return $this->inCidr($binary, '2000::', 3) ? null : 'not a public internet address';
    }

    /** The IPv4 address hidden in an IPv4-mapped or NAT64 address, as text; null for any other IPv6 address. */
    private function embeddedV4(string $binary): ?string
    {
        $isMapped = str_starts_with($binary, str_repeat("\0", 10)."\xff\xff");
        $isNat64 = str_starts_with($binary, hex2bin('0064ff9b0000000000000000'));

        if (! $isMapped && ! $isNat64) {
            return null;
        }

        return inet_ntop(substr($binary, 12, 4)) ?: null;
    }

    /** @param  array<string, string>  $table */
    private function matchAny(string $binary, array $table): ?string
    {
        foreach ($table as $cidr => $label) {
            [$network, $bits] = explode('/', $cidr);

            if ($this->inCidr($binary, $network, (int) $bits)) {
                return $label;
            }
        }

        return null;
    }

    private function inCidr(string $binary, string $network, int $bits): bool
    {
        $base = inet_pton($network);

        if ($base === false || strlen($base) !== strlen($binary)) {
            return false;
        }

        $fullBytes = intdiv($bits, 8);

        if ($fullBytes > 0 && substr($binary, 0, $fullBytes) !== substr($base, 0, $fullBytes)) {
            return false;
        }

        $rest = $bits % 8;

        if ($rest === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $rest)) & 0xFF;

        return (ord($binary[$fullBytes]) & $mask) === (ord($base[$fullBytes]) & $mask);
    }
}
