<?php

namespace App\Services\Instance;

use App\Support\ScheduleCommandName;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Puts the SystemGate in front of every scheduled event. It adds one more `when()` condition to each, so
 * the conditions an event already has (settings switches, time windows) still apply, and the gate is
 * asked afresh every time the scheduler considers the event. Called once, at the end of
 * routes/console.php, after every event has been declared.
 */
final class ScheduleGate
{
    public function __construct(private readonly SystemGate $gate) {}

    public function apply(Schedule $schedule): void
    {
        $gate = $this->gate;

        foreach ($schedule->events() as $event) {
            $command = ScheduleCommandName::for($event);

            $event->when(static fn (): bool => $gate->allowsCommand($command));
        }
    }
}
