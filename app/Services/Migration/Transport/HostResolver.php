<?php

namespace App\Services\Migration\Transport;

/** Turns a host name into the addresses it points at. An interface so tests can answer without a network. */
interface HostResolver
{
    /**
     * Every IPv4 and IPv6 address the host resolves to (ALL of them: one private answer among public ones is an attack
     * and must be seen). Empty when the name does not resolve.
     *
     * @return list<string>
     */
    public function resolve(string $host): array;
}
