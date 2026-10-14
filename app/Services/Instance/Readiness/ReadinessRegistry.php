<?php

namespace App\Services\Instance\Readiness;

use App\Services\Instance\Readiness\Checks\DataChecks;
use App\Services\Instance\Readiness\Checks\HttpChecks;
use App\Services\Instance\Readiness\Checks\IdentityChecks;
use App\Services\Instance\Readiness\Checks\ModeChecks;
use App\Services\Instance\Readiness\Checks\RuntimeChecks;
use App\Services\Instance\Readiness\Checks\SecretChecks;
use App\Services\Instance\Readiness\Checks\WorkerChecks;

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

    /**
     * @param  array<int, string>|null  $phases  only the checks of these phases (all of them when null)
     * @return array<int, ReadinessProbe>
     */
    public function probes(?array $phases = null): array
    {
        $all = $this->probes ??= array_merge(
            (new DataChecks)->probes(),
            (new IdentityChecks)->probes(),
            (new SecretChecks)->probes(),
            (new ModeChecks)->probes(),
            (new RuntimeChecks)->probes(),
            (new HttpChecks)->probes(),
            (new WorkerChecks)->probes(),
        );

        return $phases === null ? $all : array_values(array_filter($all, static fn (ReadinessProbe $p): bool => in_array($p->phase, $phases, true)));
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_map(static fn (ReadinessProbe $p): string => $p->key, $this->probes());
    }
}
