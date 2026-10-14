<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Migration\Import\Contracts\ImportStage;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportPreflightService;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStepResult;
use App\Services\Migration\Package\Exceptions\PackageException;
use App\Services\Migration\Package\Kdf;
use App\Services\Migration\Package\PackageReader;
use App\Services\Updates\PreflightCheck;
use App\Services\Updates\PreflightReport;

/**
 * Stage 4. Runs every check that can be made without changing the site and keeps the report in the state. A real run
 * then WAITS for a person (confirm()); a dry run ends here with the report. Failed checks do not fail the run: the person
 * is shown them, fixes the cause and asks for the checks again.
 *
 * Idempotent and read-only. Once the run has been confirmed the stage is a no-op, so a resumed run does not re-judge a
 * site it has already been told to replace.
 */
final class PreflightStage implements ImportStage
{
    public function __construct(
        private readonly ImportPreflightService $service,
        private readonly Kdf $kdf,
    ) {}

    public function key(): string
    {
        return 'preflight';
    }

    public function destructive(): bool
    {
        return false;
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        if ($state->flag('confirmed')) {
            return ImportStepResult::done('Confirmed.');
        }

        $entry = $context->escrow->get(ImportContext::escrowId($state->id));

        if ($entry === null) {
            return ImportStepResult::needsPassphrase('The key for this package has expired: type the passphrase again.');
        }

        try {
            $reader = PackageReader::openWithKeys((string) $state->package['path'], $entry->keys, $this->kdf);
        } catch (PackageException $e) {
            throw $e->toMigrationException();
        }

        try {
            $report = $this->service->run($state, $reader, $state->options);
        } finally {
            $reader->close();
        }

        $state->package['preflight'] = $this->summarise($report);
        $state->setFlag('preflight.done');

        return $state->kind === 'dry_run'
            ? ImportStepResult::done('The checks are done.')
            : ImportStepResult::awaitConfirmation('The checks are done. Waiting for your confirmation.');
    }

    /**
     * What is kept in the state: every check (key, status, message, and the few facts the screens need), and what the
     * person must acknowledge. No setting value, no key, no address.
     *
     * @return array<string, mixed>
     */
    private function summarise(PreflightReport $report): array
    {
        $acks = [];

        foreach ($report->warnings() as $warning) {
            if (isset($warning->meta['ack'])) {
                $acks[] = (string) $warning->meta['ack'];
            }
        }

        return [
            'at' => now()->toIso8601String(),
            'can_proceed' => $report->canProceed(),
            'required_acks' => array_values(array_unique($acks)),
            'checks' => array_map(static fn (PreflightCheck $c): array => [
                'key' => $c->key, 'label' => $c->label, 'status' => $c->status, 'message' => $c->message,
                'ack' => $c->meta['ack'] ?? null,
                'meta' => array_intersect_key($c->meta, array_flip(['class', 'business_rows', 'total_rows', 'rows', 'cap', 'needed', 'free', 'seconds', 'missing', 'missing_in_target_count', 'missing_in_package_count', 'package_version', 'target_version'])),
            ], $report->checks),
        ];
    }
}
