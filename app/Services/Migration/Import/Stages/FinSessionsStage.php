<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStepResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Stage 22. Nobody stays signed in from before: the stored sessions (database and files) are removed and the permission cache is
 * forgotten once more, now that the roles and permissions are the imported ones.
 */
final class FinSessionsStage extends FinalisingStage
{
    public function key(): string
    {
        return 'fin_sessions';
    }

    protected function after(): string
    {
        return 'fin.admins.done';
    }

    protected function flag(): string
    {
        return 'fin.sessions.done';
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        if ($state->flag($this->flag())) {
            return ImportStepResult::done('The old sessions are gone.');
        }

        $this->guard($state);

        $removed = 0;

        if (Schema::hasTable('sessions')) {
            $removed += DB::table('sessions')->delete();
        }

        $folder = (string) config('session.files', storage_path('framework/sessions'));

        foreach (glob(rtrim($folder, '/\\').DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            if (is_file($file) && ! is_link($file) && basename($file) !== '.gitignore' && @unlink($file)) {
                $removed++;
            }
        }

        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            $this->warnFailure($state, 'The permission cache could not be cleared.', $e);
        }

        $state->package['sessions_removed'] = $removed;
        $state->setFlag($this->flag());

        return ImportStepResult::done($removed.' session(s) removed.');
    }
}
