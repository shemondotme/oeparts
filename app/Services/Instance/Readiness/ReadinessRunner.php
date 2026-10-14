<?php

namespace App\Services\Instance\Readiness;

use App\Support\AtomicJsonFile;
use Illuminate\Support\Facades\Log;

/**
 * Runs the readiness checks in the order they are registered, as many as fit in a time budget, and keeps what it has found in
 * `storage/app/instance/readiness.json`, so a slow check (a count over a million rows) never has to finish inside one request and
 * a killed process loses nothing it already knew. A check that throws is recorded as a failure with the reason; it never stops the
 * others from being looked at.
 */
final class ReadinessRunner
{
    /** @param  array<int, string>  $phases  which checks this runner carries out (see ReadinessProbe) */
    public function __construct(private readonly ReadinessRegistry $registry, private readonly ?string $path = null, private readonly array $phases = [ReadinessProbe::STATIC, ReadinessProbe::LIVE]) {}

    /** The same runner, writing to the same file, for only some phases (the import runs the static ones while the site is closed). */
    public function forPhases(array $phases): self
    {
        return new self($this->registry, $this->path, $phases);
    }

    public function file(): AtomicJsonFile
    {
        return new AtomicJsonFile($this->path ?? storage_path('app/instance/readiness.json'));
    }

    /** Forget the last run and begin again at the first check. */
    public function reset(): void
    {
        $this->ensureDirectory();
        $this->file()->write(['v' => 1, 'cursor' => 0, 'checks' => [], 'complete' => false, 'started_at' => now()->toIso8601String(), 'generated_at' => now()->toIso8601String()]);
    }

    /** The report as it stands: complete or not. Null before any run. */
    public function last(): ?ReadinessReport
    {
        try {
            $data = $this->file()->read();
        } catch (\RuntimeException) {
            return null;
        }

        return $data === null ? null : ReadinessReport::fromArray($data);
    }

    /** Run every check, with no budget. @return ReadinessReport the finished report */
    public function run(ReadinessContext $context): ReadinessReport
    {
        $this->reset();

        return $this->advance($context, PHP_FLOAT_MAX);
    }

    /** Carry on from where the saved run stopped, for at most $budgetSeconds (at least one check). */
    public function advance(ReadinessContext $context, float $budgetSeconds): ReadinessReport
    {
        $data = $this->saved();
        $probes = $this->registry->probes($this->phases);
        $cursor = (int) $data['cursor'];
        $checks = array_map(static fn (array $c): ReadinessCheck => ReadinessCheck::fromArray($c), (array) $data['checks']);
        $deadline = microtime(true) + $budgetSeconds;

        do {
            if ($cursor >= count($probes)) {
                break;
            }

            $checks[] = $this->runOne($probes[$cursor], $context);
            $cursor++;
            $this->save($cursor, $checks, $cursor >= count($probes), (string) $data['started_at']);
        } while (microtime(true) < $deadline);

        return new ReadinessReport($checks, $cursor >= count($probes), now()->toIso8601String(), $this->phases);
    }

    private function runOne(ReadinessProbe $probe, ReadinessContext $context): ReadinessCheck
    {
        try {
            return $probe->run($context);
        } catch (\Throwable $e) {
            Log::warning("Readiness check {$probe->key} could not run: ".$e->getMessage());

            return new ReadinessCheck($probe->key, $probe->key, $probe->group, ReadinessCheck::FAIL, 'This check could not run: '.$e->getMessage(), fix: 'Fix the reason above and run the checks again.');
        }
    }

    /** @return array<string, mixed> */
    private function saved(): array
    {
        try {
            $data = $this->file()->read();
        } catch (\RuntimeException) {
            $data = null;
        }

        if ($data === null || ($data['complete'] ?? false) === true || (int) ($data['cursor'] ?? 0) > count($this->registry->probes($this->phases))) {
            $this->reset();
            $data = $this->file()->read() ?? [];
        }

        return $data + ['cursor' => 0, 'checks' => [], 'started_at' => now()->toIso8601String()];
    }

    /** @param  array<int, ReadinessCheck>  $checks */
    private function save(int $cursor, array $checks, bool $complete, string $startedAt): void
    {
        $this->file()->write([
            'v' => 1, 'cursor' => $cursor, 'checks' => array_map(static fn (ReadinessCheck $c): array => $c->toArray(), $checks), 'phases' => $this->phases,
            'complete' => $complete, 'started_at' => $startedAt, 'generated_at' => now()->toIso8601String(),
        ]);
    }

    private function ensureDirectory(): void
    {
        $dir = dirname($this->file()->path());

        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }
}
