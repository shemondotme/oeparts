<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStepResult;
use Illuminate\Support\Facades\Artisan;

/**
 * Stage 23 (a failure is a warning). The public link to the uploaded files (public/storage), made the way an update makes it. On
 * a host where links are not allowed it says so and leaves the way to fix it, instead of failing an import that is otherwise done.
 */
final class FinLinksStage extends FinalisingStage
{
    public function key(): string
    {
        return 'fin_links';
    }

    protected function after(): string
    {
        return 'fin.sessions.done';
    }

    protected function flag(): string
    {
        return 'fin.links.done';
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        if ($state->flag($this->flag())) {
            return ImportStepResult::done('The file link is in place.');
        }

        $this->guard($state);

        try {
            Artisan::call('storage:link');
        } catch (\Throwable $e) {
            $this->warn($state, 'The link to the uploaded files could not be made: '.$e->getMessage().' Run "php artisan storage:link" on the server.');
        }

        $public = public_path('storage');

        if (is_dir($public) && ! is_link($public)) {
            $this->warn($state, 'public/storage is a real folder, not a link to storage/app/public: uploaded files will not show until it is replaced by the link.');
        }

        $state->setFlag($this->flag());

        return ImportStepResult::done('The file link was checked.');
    }
}
