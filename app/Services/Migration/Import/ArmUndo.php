<?php

namespace App\Services\Migration\Import;

use App\Services\Backup\BackupLock;
use App\Services\Instance\InstanceEnv;
use App\Services\Migration\Import\Contracts\QueueGuard;
use App\Support\AtomicJsonFile;

/**
 * Undoes the ENVIRONMENT changes of the arm stage, in the reverse order they were made: the .env keys back to the image
 * taken before, the queue workers let go, the marker removed, the lock released. No data was touched at that point, so
 * a run that goes through here ends `failed`, never `rolled_back`.
 *
 * Every part is its own flag and is never repeated, so an undo that is killed half way carries on where it was.
 */
final class ArmUndo
{
    public function __construct(
        private readonly ImportStateStore $store,
        private readonly BackupLock $lock,
        private readonly ImportMarker $marker,
        private readonly QueueGuard $guard,
        private readonly InstanceEnv $env,
    ) {}

    public function run(ImportState $state): void
    {
        if ($state->flag('arm.begin') && ! $state->flag('undo.env')) {
            $image = (new AtomicJsonFile($this->store->directory($state->id).DIRECTORY_SEPARATOR.'journal'.DIRECTORY_SEPARATOR.'env-before.json'))->read();

            if ($image !== null) {
                $this->env->restore($image, 'arm-undo:'.$state->id);
            }

            $this->done($state, 'undo.env');
        }

        if (($state->flag('arm.pause') || $state->flag('arm.repause')) && ! $state->flag('undo.queue')) {
            $this->guard->resume();
            $this->done($state, 'undo.queue');
        }

        if ($state->flag('arm.marker') && ! $state->flag('undo.marker')) {
            $this->marker->remove();
            $this->done($state, 'undo.marker');
        }

        if ($state->flag('arm.lock') && ! $state->flag('undo.lock')) {
            $this->lock->releaseIfOwnedBy('import:'.$state->id);
            $this->done($state, 'undo.lock');
        }
    }

    private function done(ImportState $state, string $flag): void
    {
        $state->setFlag($flag);
        $this->store->save($state);
    }
}
