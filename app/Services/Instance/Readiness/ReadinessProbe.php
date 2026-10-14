<?php

namespace App\Services\Instance\Readiness;

/**
 * One check, ready to run: its name, its group and the function that looks. A probe never throws (a check that cannot do its
 * job answers `fail` or `skip` with the reason); the runner still guards against it, so one broken check cannot hide the others.
 */
final class ReadinessProbe
{
    /** @param  \Closure(ReadinessContext): ReadinessCheck  $run */
    public function __construct(public readonly string $key, public readonly string $group, private readonly \Closure $run) {}

    public function run(ReadinessContext $context): ReadinessCheck
    {
        return ($this->run)($context);
    }
}
