<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\ImportStepResult;
use App\Services\Migration\Import\ImportTarget;
use App\Services\Migration\Rewrite\DomainRewriter;
use App\Services\Migration\Rewrite\RewriteCursor;
use App\Services\Migration\Rewrite\RewritePlan;
use App\Support\AtomicJsonFile;

/**
 * Stage 16. Every address of the old site that is written into the content (pages, menus, product descriptions, redirects,
 * settings...) becomes the new address, by the domain rewriter: query builder only (no observers, no activity log, no IndexNow
 * ping), one committed chunk at a time, a cursor in a file, and an optimistic write that makes a repeat a no-op, so a killed or
 * resumed run ends exactly like an uninterrupted one.
 *
 * With nothing to rewrite (the package does not say where it came from, or it came from the very address it is moving to) the
 * stage says so and finishes.
 */
final class FinRewriteStage extends FinalisingStage
{
    public function __construct(
        ImportMarker $marker,
        private readonly DomainRewriter $rewriter,
        private readonly ImportStateStore $store,
    ) {
        parent::__construct($marker);
    }

    public function key(): string
    {
        return 'fin_rewrite';
    }

    protected function after(): string
    {
        return 'fin.migrations.done';
    }

    protected function flag(): string
    {
        return 'fin.rewrite.done';
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        if ($state->flag($this->flag())) {
            return ImportStepResult::done('The addresses are rewritten.');
        }

        $this->guard($state);

        $target = ImportTarget::of($state);
        $from = $target->rewriteFrom();

        if ($from === []) {
            $state->package['rewrite'] = ['skipped' => true, 'rows_changed' => 0, 'replacements' => 0, 'invalid_json' => 0, 'concurrent_skips' => 0];
            $state->setFlag($this->flag());

            return ImportStepResult::done('There is no old address to replace.');
        }

        $plan = new RewritePlan($from, $target->url);
        $file = new AtomicJsonFile($this->store->directory($state->id).DIRECTORY_SEPARATOR.'rewrite.cursor.json');
        $cursor = RewriteCursor::load($file, $plan);

        $this->rewriter->advance($plan, $cursor, max(0.0, $context->secondsLeft()), false, function (RewriteCursor $c) use ($file, $state): void {
            $c->save($file);
            $state->touch();
        });

        if (! $cursor->done) {
            $steps = max(1, count($this->rewriter->steps()));

            return ImportStepResult::progress(min(0.99, $cursor->step / $steps), 'Rewriting addresses.');
        }

        $state->package['rewrite'] = [
            'skipped' => false, 'from' => $from, 'rows_changed' => $cursor->total('rows_changed'), 'replacements' => $cursor->total('replacements'),
            'invalid_json' => $cursor->total('invalid_json'), 'concurrent_skips' => $cursor->total('concurrent_skips'),
        ];
        $state->setFlag($this->flag());

        return ImportStepResult::done($cursor->total('rows_changed').' row(s) rewritten.');
    }
}
