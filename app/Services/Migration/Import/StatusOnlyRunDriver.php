<?php

namespace App\Services\Migration\Import;

use App\Services\Migration\Import\Contracts\RunDriver;

/**
 * The driver the runner routes use until the import manager exists: it reports what the state file says and does no
 * work. Also the document shape every driver returns: nothing in it is a secret, a path or a setting value.
 */
final class StatusOnlyRunDriver implements RunDriver
{
    public function __construct(private readonly ImportStateStore $store) {}

    public function advance(string $runId, float $budgetSeconds): array
    {
        return $this->status($runId) + ['advanced' => false];
    }

    public function status(string $runId): array
    {
        $state = $this->store->find($runId);

        if ($state === null) {
            return ['found' => false];
        }

        return [
            'found' => true,
            'id' => $state->id,
            'kind' => $state->kind,
            'status' => $state->status->value,
            'stage' => $state->stage,
            'terminal' => $state->status->isTerminal(),
            'needs_attention' => $state->status->needsAttention(),
            'stalled' => is_file($this->store->directory($runId).DIRECTORY_SEPARATOR.'stalled.flag'),
            'destructive' => $state->isDestructive(),
            'heartbeat_at' => $state->heartbeatAt,
            'error' => $state->error,
        ];
    }
}
