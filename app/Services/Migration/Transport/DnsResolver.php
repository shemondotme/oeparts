<?php

namespace App\Services\Migration\Transport;

use Illuminate\Support\Facades\Log;

/** The real resolver: A and AAAA records, with the system resolver (IPv4 only) as the fallback. */
class DnsResolver implements HostResolver
{
    public function resolve(string $host): array
    {
        $found = [];

        try {
            foreach (@dns_get_record($host, DNS_A + DNS_AAAA) ?: [] as $record) {
                $address = $record['ip'] ?? $record['ipv6'] ?? null;

                if (is_string($address) && $address !== '') {
                    $found[] = $address;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('migration: DNS lookup failed, trying the system resolver', ['error' => $e->getMessage()]);
        }

        if ($found === []) {
            $found = @gethostbynamel($host) ?: [];
        }

        return array_values(array_unique($found));
    }
}
