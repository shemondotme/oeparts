<?php

namespace App\Services\Instance;

use App\Jobs\RegenerateSitemap;
use App\Services\Instance\Readiness\ReadinessCheck;
use App\Services\Instance\Readiness\ReadinessContext;
use App\Services\Instance\Readiness\ReadinessReport;
use App\Services\Instance\Readiness\ReadinessRunner;
use App\Services\Migration\Import\ImportSettings;
use App\Services\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The two ways a moved-in site gets out of its safe mode, each behind the checks that make it safe:
 *
 *   release()  quarantine -> production (a live launch) or staging (a copy). The readiness report, static and live, must have no
 *              failure left (an overridable one may be waived with a typed reason, recorded; the worker's mode never may) and
 *              nothing pending;
 *   goLive()   staging -> production. Everything release() asks, plus: the person types the site's own host, there is no activity
 *              from the copy's time as a staging site (or they accept that it is discarded), real payments are confirmed on
 *              purpose, and the canonical host is put back the way the live launch rule decides.
 *
 * The mode itself is changed by InstanceModeSwitcher, which restarts the workers first and waits until one agrees. A worker that
 * answers with the OLD mode for the whole wait means mail would go the wrong way: the change is undone and the site stays as it was.
 */
class InstancePromoter
{
    public function __construct(
        private readonly SafeMode $safe,
        private readonly ReadinessRunner $runner,
        private readonly InstanceModeSwitcher $switcher,
        private readonly ActivityDiscarder $discarder,
        private readonly InstanceState $ledger,
        private readonly CanonicalHostPolicy $canonical,
        private readonly InstanceCacheReset $caches,
        private readonly ImportSettings $settings,
        private readonly SettingsService $settingsCache,
    ) {}

    /**
     * Run every readiness check now (static and live) and keep the report.
     */
    public function readiness(?string $requestHost = null): ReadinessReport
    {
        return $this->runner->run(ReadinessContext::make(null, $requestHost));
    }

    /**
     * @param  array<string, string>  $waivers  check key => the reason typed by the person
     * @return array{from: string, to: string, worker: string, waived: array<int, string>}
     *
     * @throws PromotionBlockedException
     */
    public function release(InstanceMode $to, array $waivers = [], string $by = 'command line', ?string $requestHost = null): array
    {
        $from = $this->safe->mode();

        if ($from !== InstanceMode::Quarantine || $to === InstanceMode::Quarantine) {
            throw new PromotionBlockedException('not_quarantine', 'Only a site in quarantine can be released, and only to production or to staging.', ['mode' => $from->value, 'to' => $to->value]);
        }

        $report = $this->readiness($requestHost);
        $this->assertCanRelease($report, $waivers);
        $this->recordWaivers($report, $waivers, $by);

        $switched = $this->switchOrUndo($to, $by);

        if ($to === InstanceMode::Production) {
            $this->ledger->clearWatermarks();
            $this->afterGoingLive($by);
        }

        return ['from' => $from->value, 'to' => $to->value, 'worker' => $switched['worker'], 'waived' => array_keys($waivers)];
    }

    /**
     * @param  array{discard_activity?: bool, confirm_payments?: bool, waivers?: array<string, string>}  $options
     * @return array{discarded: array<string, int>, worker: string, canonical_host: string, waived: array<int, string>}
     *
     * @throws PromotionBlockedException
     */
    public function goLive(string $typedHost, array $options = [], string $by = 'command line', ?string $requestHost = null): array
    {
        if ($this->safe->mode() !== InstanceMode::Staging) {
            throw new PromotionBlockedException('not_staging', 'Only a staging copy can go live. A site in quarantine is released instead; the real shop is already live.', ['mode' => $this->safe->mode()->value]);
        }

        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        if ($host === '' || strtolower(trim($typedHost)) !== $host) {
            throw new PromotionBlockedException('host_not_typed', 'Type the host of this site to confirm going live.', ['expected' => $host]);
        }

        $discarded = $this->dealWithActivity((bool) ($options['discard_activity'] ?? false));

        if (strtolower((string) $this->settings->stored('payment', 'airwallex_environment')) === 'live' && ! ($options['confirm_payments'] ?? false)) {
            throw new PromotionBlockedException('payments_unconfirmed', 'The card gateway is set to its LIVE environment: going live starts taking real money. Confirm that on purpose.', ['gateway' => 'airwallex']);
        }

        $waivers = (array) ($options['waivers'] ?? []);
        $report = $this->readiness($requestHost);
        $this->assertCanRelease($report, $waivers);
        $this->recordWaivers($report, $waivers, $by);

        $canonical = $this->canonical->resolve(CanonicalHostPolicy::LIVE, (string) ($this->ledger->read()['import']['source_canonical_host'] ?? ''), $host);
        $switched = $this->switchOrUndo(InstanceMode::Production, $by);

        $this->settings->put('seo', 'canonical_host', $canonical);
        $this->ledger->clearWatermarks();
        $this->afterGoingLive($by);

        return ['discarded' => $discarded, 'worker' => $switched['worker'], 'canonical_host' => $canonical, 'waived' => array_keys($waivers)];
    }

    /** @return array<string, int> what was discarded */
    private function dealWithActivity(bool $discard): array
    {
        $activity = $this->discarder->activity();

        if ($activity === []) {
            return [];
        }

        if (! $discard) {
            throw new PromotionBlockedException('activity_present', 'This copy has been used since it was made: '.implode(', ', array_map(fn (string $t, int $n): string => "{$n} in {$t}", array_keys($activity), $activity)).'. Going live would carry it into the real shop. Discard it first, or go live with the discard option.', ['activity' => $activity]);
        }

        return $this->discarder->discard()['deleted'];
    }

    /**
     * @param  array<string, string>  $waivers
     *
     * @throws PromotionBlockedException
     */
    private function assertCanRelease(ReadinessReport $report, array $waivers): void
    {
        foreach ($waivers as $key => $reason) {
            $check = $report->find((string) $key);

            if ($check === null || ! $check->overridable) {
                throw new PromotionBlockedException('waiver_refused', "The check \"{$key}\" cannot be waived.", ['key' => $key]);
            }

            if (strlen(trim((string) $reason)) < 10) {
                throw new PromotionBlockedException('waiver_refused', "Waiving \"{$key}\" needs a reason of at least ten characters.", ['key' => $key]);
            }
        }

        if (! $report->canRelease(array_map('strval', array_keys($waivers)))) {
            $blocking = array_values(array_filter($report->checks, static fn (ReadinessCheck $c): bool => $c->status === ReadinessCheck::PENDING || $c->isFail()));

            throw new PromotionBlockedException('readiness_blocked', 'The launch checks are not all clear: '.implode(', ', array_map(static fn (ReadinessCheck $c): string => $c->key.' ('.$c->status.')', $blocking)).'.', ['checks' => array_map(static fn (ReadinessCheck $c): array => $c->toArray(), $blocking), 'complete' => $report->complete]);
        }
    }

    /** @param  array<string, string>  $waivers */
    private function recordWaivers(ReadinessReport $report, array $waivers, string $by): void
    {
        if ($waivers === []) {
            return;
        }

        $this->ledger->update(function (array $ledger) use ($waivers, $by): array {
            foreach ($waivers as $key => $reason) {
                $ledger['waivers'][] = ['key' => (string) $key, 'reason' => trim((string) $reason), 'by' => $by, 'at' => now()->toIso8601String()];
            }

            return $ledger;
        });
    }

    /**
     * @return array{from: string, to: string, worker: string, wrote: bool}
     *
     * @throws PromotionBlockedException a worker kept the old mode: the change was undone
     */
    private function switchOrUndo(InstanceMode $to, string $by): array
    {
        $from = $this->safe->mode();
        $result = $this->switcher->switch($to, $by);

        if ($result['worker'] === InstanceModeSwitcher::WORKER_STALE) {
            $this->switcher->switch($from, $by.' (undoing: workers did not follow)', 1);

            throw new PromotionBlockedException('workers_stale', 'A queue worker kept the old mode, so mail would go the wrong way. The change was undone. Restart the workers and try again.', ['from' => $from->value, 'to' => $to->value]);
        }

        return $result;
    }

    /** What changes the moment the site is really the shop: forget what was cached under the safe mode and rebuild the sitemap. */
    private function afterGoingLive(string $by): void
    {
        $this->caches->reset();

        foreach (Schema::hasTable('settings') ? DB::table('settings')->distinct()->pluck('group') : [] as $group) {
            $this->settingsCache->forget((string) $group);
        }

        try {
            RegenerateSitemap::dispatch($by);
        } catch (\Throwable) {
            // The nightly schedule builds it anyway; going live must not fail because the sitemap could not be queued.
        }
    }
}
