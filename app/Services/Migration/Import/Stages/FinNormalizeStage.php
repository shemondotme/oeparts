<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportSettings;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\ImportStepResult;
use App\Services\Migration\Import\ImportTarget;
use App\Services\Migration\Import\PostImportNormalizer;

/**
 * Stage 17. The few settings that must say something different on the new site (its address, its canonical host, the Search
 * Console property, maintenance, the sign-in code), written after the rewrite so these explicit values win. The canonical host
 * of the source is put in the run's state BEFORE anything is changed: a run killed half way must still know what it started
 * from. What was adjusted is listed, by name, for the report.
 */
final class FinNormalizeStage extends FinalisingStage
{
    public function __construct(
        ImportMarker $marker,
        private readonly PostImportNormalizer $normalizer,
        private readonly ImportSettings $settings,
        private readonly ImportStateStore $store,
    ) {
        parent::__construct($marker);
    }

    public function key(): string
    {
        return 'fin_normalize';
    }

    protected function after(): string
    {
        return 'fin.rewrite.done';
    }

    protected function flag(): string
    {
        return 'fin.normalize.done';
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        if ($state->flag($this->flag())) {
            return ImportStepResult::done('The settings are adjusted.');
        }

        $this->guard($state);

        if (! array_key_exists('source_canonical_host', $state->package)) {
            $state->package['source_canonical_host'] = (string) ($this->settings->stored('seo', 'canonical_host') ?? '');
            $this->store->save($state);
        }

        $adjusted = $this->normalizer->apply($state, ImportTarget::of($state));

        $state->package['normalized'] = $adjusted;
        $state->setFlag($this->flag());

        return ImportStepResult::done(count($adjusted).' setting(s) adjusted.');
    }
}
