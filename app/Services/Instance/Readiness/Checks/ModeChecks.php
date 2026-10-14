<?php

namespace App\Services\Instance\Readiness\Checks;

use App\Services\Instance\Capability;
use App\Services\Instance\InstanceMode;
use App\Services\Instance\Readiness\ReadinessCheck as C;
use App\Services\Instance\Readiness\ReadinessContext;
use App\Services\Instance\Readiness\ReadinessProbe;
use App\Services\Instance\SystemGate;

/**
 * Does the site behave the way its mode says? On a copy (staging or quarantine) the checks PROVE it with no side effect: a mail to
 * a stranger is dropped, the trackers are blank, crawlers are kept out, a customer-facing scheduled task is held. On the real
 * shop they only report that nothing is being held back.
 */
final class ModeChecks
{
    private const GROUP = 'mode';

    /** @return array<int, ReadinessProbe> */
    public function probes(): array
    {
        return [
            new ReadinessProbe('mode_self_test', self::GROUP, $this->selfTest(...)),
            new ReadinessProbe('mail_state', self::GROUP, $this->mailState(...)),
            new ReadinessProbe('payments_state', self::GROUP, $this->paymentsState(...)),
            new ReadinessProbe('indexing_state', self::GROUP, $this->indexingState(...)),
            new ReadinessProbe('cache_isolation', self::GROUP, $this->cacheIsolation(...)),
        ];
    }

    /** Behavioural proofs for a copy: each is something the copy must refuse, tried for real and with no effect. */
    public function selfTest(ReadinessContext $c): C
    {
        $label = 'This copy really holds back what it should';

        if (! $c->safe->isCopy()) {
            return $c->check('mode_self_test', $label, self::GROUP, C::SKIP, 'This is the real shop: there is nothing to hold back.');
        }

        $failures = [];

        if ($c->safe->mailVerdict('nobody@stranger.invalid') !== 'drop') {
            $failures[] = 'a mail to a stranger would be delivered';
        }

        if (($c->safe->maskSettings('integrations', ['gtm_id' => 'GTM-REAL'])['gtm_id'] ?? 'GTM-REAL') !== '') {
            $failures[] = 'the tracking id is not hidden';
        }

        if ($c->safe->robotsDirective() === null) {
            $failures[] = 'search engines are not kept out';
        }

        if ($c->safe->scheduledAllowed(SystemGate::CUSTOMER)) {
            $failures[] = 'tasks that reach customers are not held';
        }

        if ($c->safe->allows(Capability::UsePaysera)) {
            $failures[] = 'the Paysera gateway would be used';
        }

        return $failures === []
            ? $c->check('mode_self_test', $label, self::GROUP, C::PASS, 'Mail, trackers, crawlers, customer tasks and Paysera are all held back.')
            : $c->check('mode_self_test', $label, self::GROUP, C::FAIL, 'This copy does not hold back everything: '.implode('; ', $failures).'.', ['failed' => $failures], 'Check OE_MODE and the instance settings in .env.');
    }

    public function mailState(ReadinessContext $c): C
    {
        $label = 'How mail is handled';
        $mode = $c->safe->mode();

        $text = match ($mode) {
            InstanceMode::Production => 'Mail is sent normally.',
            InstanceMode::Quarantine => 'No mail is sent while the site is in quarantine.',
            InstanceMode::Staging => $c->safe->mailAllowList() === [] ? 'No mail is sent from this copy.' : 'Mail goes only to '.count($c->safe->mailAllowList()).' allow-listed address(es) or domain(s).',
        };

        return $c->check('mail_state', $label, self::GROUP, C::PASS, $text);
    }

    public function paymentsState(ReadinessContext $c): C
    {
        $label = 'How payments are handled';
        $text = match ($c->safe->mode()) {
            InstanceMode::Production => 'Payments are live.',
            InstanceMode::Quarantine => 'No payment can be taken while the site is in quarantine.',
            InstanceMode::Staging => 'Card payments use the gateway sandbox only; Paysera is refused; payments copied from the real shop cannot be captured or released.',
        };

        return $c->check('payments_state', $label, self::GROUP, C::PASS, $text);
    }

    public function indexingState(ReadinessContext $c): C
    {
        $label = 'Whether search engines are let in';

        return $c->check('indexing_state', $label, self::GROUP, C::PASS, $c->safe->robotsDirective() === null ? 'The site tells search engines they are welcome.' : 'The site tells search engines to stay away and sends no change pings.');
    }

    /** A copy must not share cache keys with the site it came from or with other sites on the same cache server. */
    public function cacheIsolation(ReadinessContext $c): C
    {
        $label = 'The cache is not shared with other sites';

        if ($c->safe->isProduction()) {
            return $c->check('cache_isolation', $label, self::GROUP, C::SKIP, 'This is the real shop.');
        }

        $id = (string) config('instance.id');
        $prefix = (string) config('cache.prefix');

        if ($id === '' || ! str_contains($prefix, $id)) {
            return $c->check('cache_isolation', $label, self::GROUP, C::FAIL, 'The cache prefix does not contain this copy\'s identity: it could read and overwrite the cache of another site.', fix: 'Set OE_INSTANCE_ID and CACHE_PREFIX in .env (the import does this).');
        }

        $shared = (string) config('cache.default') === 'redis' || (string) config('cache.default') === 'memcached';

        return $shared
            ? $c->check('cache_isolation', $label, self::GROUP, C::WARN, 'The cache lives on a server other sites may use; this copy\'s prefix keeps its keys apart.')
            : $c->check('cache_isolation', $label, self::GROUP, C::PASS, 'The cache is private to this installation.');
    }
}
