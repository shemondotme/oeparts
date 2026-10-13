<?php

namespace App\Services\Migration\Export\Transform;

use App\Services\Migration\Export\ExportPlan;

/**
 * Builds the rules an export applies, from its plan. Order matters and is fixed here: references that
 * would dangle are cut first, then secrets are removed or sealed away, then the settings and credentials
 * that must not travel are neutralised, then (for a scrambled copy) personal data is replaced, then (for a new store) numbering is reset.
 */
final class ExportPipelineFactory
{
    /**
     * @param  \Closure(array{group: string, key: string, value: string}): void|null  $secretSink  where included secrets go
     * @return array{pipeline: TransformPipeline, secrets: SettingsSecretsTransformer}
     */
    public static function forPlan(ExportPlan $plan, ?\Closure $secretSink, ?string $anonymiseSalt = null): array
    {
        if ($plan->anonymise && ($anonymiseSalt === null || $anonymiseSalt === '')) {
            throw new \RuntimeException('A scrambled export needs its per-export salt; the run has none.');
        }

        $secrets = new SettingsSecretsTransformer($plan->includeSecrets, $plan->includeSecrets ? $secretSink : null);

        $rules = [
            new DanglingFkNullifier($plan->nullified),
            $secrets,
            new MaintenanceNeutraliser,
            new CredentialScrubber,
        ];

        if ($plan->anonymise) {
            $rules[] = new StagingAnonymiser(new Pseudonymizer((string) $anonymiseSalt));
        }

        if ($plan->resetSequences) {
            $rules[] = new SequenceResetTransformer;
        }

        return ['pipeline' => new TransformPipeline($rules), 'secrets' => $secrets];
    }
}
