<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Install\InstallManager;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStepResult;

/**
 * Stage 26 (installer door only). A freshly installed site has no backup key yet and a package never carries the old site's key,
 * so without one the first backup of the new site would fail with "mandatorily encrypted, OE_BACKUP_KEY is not set". This gives
 * the site its OWN key, once: an existing key is never replaced (backups already made with it would become unreadable). On the
 * admin and command-line doors the site already has its key and nothing happens.
 */
final class FinBackupKeyStage extends FinalisingStage
{
    public function __construct(ImportMarker $marker, private readonly InstallManager $installer)
    {
        parent::__construct($marker);
    }

    public function key(): string
    {
        return 'fin_backup_key';
    }

    protected function after(): string
    {
        return 'fin.queue.done';
    }

    protected function flag(): string
    {
        return 'fin.backup_key.done';
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        if ($state->flag($this->flag())) {
            return ImportStepResult::done('The backup key is in order.');
        }

        $this->guard($state);

        $generated = ($state->options['door'] ?? '') === 'installer' && $this->installer->ensureBackupKey();

        $state->package['backup_key'] = ['generated' => $generated];
        $state->setFlag($this->flag());

        return ImportStepResult::done($generated ? 'A backup key was generated for this site.' : 'Nothing to do for the backup key.');
    }
}
