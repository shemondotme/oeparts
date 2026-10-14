<?php

namespace App\Services\Instance\Readiness;

/**
 * One check, ready to run: its name, its group, the phase it belongs to and the function that looks. A probe never throws (a check
 * that cannot do its job answers `fail` or `skip` with the reason); the runner still guards against it, so one broken check cannot
 * hide the others.
 *
 * Phase A needs nothing but the data and the settings and can run while the site is closed (the import runs it itself). Phase B
 * needs the site to answer over HTTP and a worker to pick up a job, so it can only run once the site is open.
 */
final class ReadinessProbe
{
    public const STATIC = 'A';

    public const LIVE = 'B';

    /** @param  \Closure(ReadinessContext): ReadinessCheck  $run */
    public function __construct(public readonly string $key, public readonly string $group, private readonly \Closure $run, public readonly string $phase = self::STATIC) {}

    public function run(ReadinessContext $context): ReadinessCheck
    {
        return ($this->run)($context);
    }
}
