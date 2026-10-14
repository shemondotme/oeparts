<?php

namespace App\Services\Instance;

use App\Services\Instance\Contracts\InstanceBegin;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportTarget;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;

/**
 * What an import does to the INSTALLATION (not its data) the moment it closes the site: the new address, the quarantine
 * mode, an identity of its own and cache prefixes of its own, written to .env through InstanceEnv, which signals the queue
 * workers to restart first. Called by the arm stage, before the first statement that touches data.
 *
 * Everything is checked before anything is written (a bad address fails here, where the undo is cheap), and the whole
 * call is repeatable: a resumed arm stage writes the same values again.
 *
 * The site stays in quarantine until the launch checks pass; the mode it is MEANT to end in (production or staging) is kept in
 * the ledger as the intent, together with where the site came from.
 */
final class InstanceBeginner implements InstanceBegin
{
    public function __construct(private readonly InstanceEnv $env, private readonly InstanceState $ledger) {}

    public function begin(ImportState $state): void
    {
        $target = ImportTarget::of($state);
        $id = $this->instanceId($state);
        $prefix = 'oe_'.$id.'_';

        $values = [
            'OE_MODE' => InstanceMode::Quarantine->value,
            'APP_URL' => $target->url,
            'OE_INSTANCE_ID' => $id,
            'CACHE_PREFIX' => $prefix,
            'REDIS_PREFIX' => $prefix,
        ];

        $allowList = trim((string) ($state->options['mail_allowlist'] ?? ''));

        if ($allowList !== '') {
            $values['OE_MAIL_ALLOWLIST'] = $allowList;
        }

        $this->env->apply($values, 'import:'.$state->id);

        $this->ledger->update(function (array $ledger) use ($state, $target): array {
            $ledger['source'] = [
                'domain' => $target->sourceHost !== '' ? $target->sourceHost : null,
                'url' => $state->package['source']['url'] ?? null,
                'package' => $state->package['fingerprint'] ?? null,
            ];
            $ledger['import'] = [
                'run_id' => $state->id,
                'started_at' => now()->toIso8601String(),
                'launch' => $target->launch,
                'intent' => $target->intent(),
                'url' => $target->url,
            ];

            return $ledger;
        });
    }

    /** A short identity for this installation: the one the person gave, else one derived from the run. */
    private function instanceId(ImportState $state): string
    {
        $given = (string) ($state->options['instance_id'] ?? '');

        if ($given === '') {
            return 'i'.substr($state->id, 0, 8);
        }

        if (preg_match('/^[a-z0-9]{3,24}$/', $given) !== 1) {
            throw new MigrationException(MigrationErrorCode::SiteUrlInvalid, ['what' => 'instance_id']);
        }

        return $given;
    }
}
