<?php

namespace App\Services\Instance\Readiness;

use App\Services\Instance\Readiness\Checks\DataChecks;
use App\Services\Instance\Readiness\Checks\IdentityChecks;
use App\Services\Instance\Readiness\Checks\ModeChecks;
use App\Services\Instance\Readiness\Checks\RuntimeChecks;
use App\Services\Instance\Readiness\Checks\SecretChecks;

/**
 * The checks of the launch-readiness report, in the order they run, from the data to the running site. One list for the
 * command line, the admin page and the import's own finishing stage, so they can never disagree about what "ready" means.
 */
final class ReadinessRegistry
{
    /** @var array<int, ReadinessProbe>|null */
    private ?array $probes = null;

    /** @param  array<int, ReadinessProbe>|null  $probes  a ready-made list (tests); the application's own when null */
    public function __construct(?array $probes = null)
    {
        $this->probes = $probes;
    }

    /** @return array<int, ReadinessProbe> */
    public function probes(): array
    {
        return $this->probes ??= array_merge(
            (new DataChecks)->probes(),
            (new IdentityChecks)->probes(),
            (new SecretChecks)->probes(),
            (new ModeChecks)->probes(),
            (new RuntimeChecks)->probes(),
        );
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_map(static fn (ReadinessProbe $p): string => $p->key, $this->probes());
    }
}
