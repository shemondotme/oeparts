<?php

namespace App\Services\Instance\Contracts;

use App\Services\Migration\Import\ImportState;

/**
 * Puts this installation into the mode an import needs while it runs: quarantine, with its own instance id, cache
 * prefixes and mail policy, written to .env through InstanceEnv. Called once, while the site is being closed.
 *
 * The import's arm stage calls it through this interface. Until the real implementation lands with the finalisation
 * of the import, the application binds NullInstanceBegin, which changes nothing.
 */
interface InstanceBegin
{
    public function begin(ImportState $state): void;
}
