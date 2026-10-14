<?php

namespace App\Services\Instance\Readiness\Checks;

use App\Services\Instance\Readiness\InstanceQueueProbe;
use App\Services\Instance\Readiness\ReadinessCheck as C;
use App\Services\Instance\Readiness\ReadinessContext;
use App\Services\Instance\Readiness\ReadinessProbe;

/**
 * Is a queue worker really there, and does it know what mode the site is in? Both are answered by one probe job that a worker
 * picks up and reports from its own point of view. A worker reads .env once when it starts, so after a mode change a worker that
 * was not restarted would go on dropping every customer mail (or sending them) under the OLD mode; that is the one thing here
 * that can never be waived.
 */
final class WorkerChecks
{
    private const GROUP = 'runtime';

    /** @return array<int, ReadinessProbe> */
    public function probes(): array
    {
        return [
            new ReadinessProbe('queue_worker', self::GROUP, $this->worker(...), ReadinessProbe::LIVE),
            new ReadinessProbe('queue_worker_mode', self::GROUP, $this->workerMode(...), ReadinessProbe::LIVE),
        ];
    }

    /** A worker picks up a job within the wait time. Without one, mail and invoices queue up and are never sent. */
    public function worker(ReadinessContext $c): C
    {
        $label = 'A queue worker is running';
        $probe = app(InstanceQueueProbe::class);

        if (! $probe->available()) {
            return $c->check('queue_worker', $label, self::GROUP, C::SKIP, 'The queue is "sync": jobs run inside the request that creates them, so there is no worker to look for.');
        }

        $answer = $this->answer($c);

        return $answer !== null
            ? $c->check('queue_worker', $label, self::GROUP, C::PASS, 'A worker picked up the probe job.')
            : $c->check('queue_worker', $label, self::GROUP, C::WARN, 'No worker picked up the probe job in time: mail, invoices and other background work would wait.', fix: 'Start a queue worker (php artisan queue:work, or the supervisor service).', overridable: true);
    }

    /** The worker runs in the mode and as the installation that .env says now. This failure cannot be waived. */
    public function workerMode(ReadinessContext $c): C
    {
        $label = 'The worker knows the mode of the site';
        $probe = app(InstanceQueueProbe::class);

        if (! $probe->available()) {
            return $c->check('queue_worker_mode', $label, self::GROUP, C::SKIP, 'The queue is "sync": there is no worker, and a job runs in the mode of the request itself.');
        }

        $answer = $this->answer($c);

        if ($answer === null) {
            return $c->check('queue_worker_mode', $label, self::GROUP, C::SKIP, 'No worker answered, so there is none to be out of date (see the check before).');
        }

        $mode = $c->safe->mode()->value;
        $id = (string) config('instance.id');
        $workerMode = (string) ($answer['mode'] ?? '');
        $workerId = (string) ($answer['instance_id'] ?? '');

        if ($workerMode !== $mode || $workerId !== $id) {
            return $c->check('queue_worker_mode', $label, self::GROUP, C::FAIL, "A worker is running as \"{$workerMode}\" while the site is \"{$mode}\"".($workerId !== $id ? ' (and as another installation)' : '').': it was started before the mode changed and would send or drop mail the wrong way.', ['site' => $mode, 'worker' => $workerMode], 'Restart the queue workers (php artisan queue:restart, or restart the supervisor service), then run the checks again.');
        }

        return $c->check('queue_worker_mode', $label, self::GROUP, C::PASS, "The worker agrees: \"{$mode}\".");
    }

    /** @return array<string, mixed>|null */
    private function answer(ReadinessContext $c): ?array
    {
        /** @var array<string, mixed>|null $answer */
        $answer = $c->memo('queue_probe', fn (): ?array => app(InstanceQueueProbe::class)->ask());

        return $answer;
    }
}
