<?php

namespace Tests\Support;

use App\Services\Migration\Transport\HostResolver;

/**
 * Answers host lookups from a table, never from the network. Each host maps to the answers of its 1st, 2nd, ... lookup (the
 * last one repeats), so a rebinding server can be played; '*' is the answer for any host not listed.
 */
final class FakeHostResolver implements HostResolver
{
    /** @var array<string, int> */
    public array $lookups = [];

    /** @param  array<string, list<list<string>>>  $answers */
    public function __construct(private readonly array $answers = ['*' => [['93.184.216.34']]]) {}

    public function resolve(string $host): array
    {
        $n = $this->lookups[$host] = ($this->lookups[$host] ?? 0) + 1;
        $sequence = $this->answers[$host] ?? $this->answers['*'] ?? [[]];

        return $sequence[min($n, count($sequence)) - 1];
    }
}
