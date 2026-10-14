<?php

namespace App\Services\Migration\Transport;

use Illuminate\Support\Carbon;

/**
 * Drives a transfer from a process that can wait (the command line): advance, report, wait out a pause, repeat until the
 * transfer ends, the time given is used up, or the caller asks to stop. Nothing here is needed for the state to survive:
 * stopping at any point leaves a transfer that the next call carries on.
 */
final class TransferDriver
{
    /** @param  \Closure(int): void|null  $sleepMilliseconds  replaced in tests */
    public function __construct(private readonly TransferManager $manager, private readonly ?\Closure $sleepMilliseconds = null) {}

    /**
     * @param  callable(array<string, mixed>): void  $onProgress  called whenever what a person would see changes
     * @param  (callable(): bool)|null  $shouldStop
     */
    public function drive(string $id, ?float $maxSeconds, callable $onProgress, ?callable $shouldStop = null, float $stepSeconds = 20.0): TransferState
    {
        $started = microtime(true);
        $seen = null;

        while (true) {
            $state = $this->manager->advance($id, $stepSeconds);
            $progress = $this->manager->progress($state);
            $key = json_encode([$progress['status'], $progress['percent'], $progress['message']]);

            if ($key !== $seen) {
                $onProgress($progress);
                $seen = $key;
            }

            if ($state->status->isTerminal()) {
                return $state;
            }

            if (($shouldStop !== null && $shouldStop()) || ($maxSeconds !== null && microtime(true) - $started >= $maxSeconds)) {
                return $state;
            }

            $this->sleep($this->waitMilliseconds($state));
        }
    }

    private function waitMilliseconds(TransferState $state): int
    {
        if ($state->status === TransferStatus::Paused && $state->nextAttemptAt !== null) {
            $seconds = max(0.0, (float) Carbon::now()->diffInSeconds(Carbon::parse($state->nextAttemptAt), false));

            return (int) min(5000, max(200, $seconds * 1000));
        }

        // another process holds the transfer, or a step ended without moving: do not spin
        return 500;
    }

    private function sleep(int $milliseconds): void
    {
        $this->sleepMilliseconds !== null ? ($this->sleepMilliseconds)($milliseconds) : usleep($milliseconds * 1000);
    }
}
