<?php

namespace App\Services\Instance\Readiness\Checks;

use App\Services\Instance\InstanceMode;
use App\Services\Instance\Readiness\ReadinessCheck as C;
use App\Services\Instance\Readiness\ReadinessContext;
use App\Services\Instance\Readiness\ReadinessProbe;
use App\Services\Migration\Import\SequenceFloor;
use Illuminate\Support\Facades\DB;

/**
 * Does the site know who and where it is? The address, the host it answers on, the canonical host and the numbering.
 */
final class IdentityChecks
{
    private const GROUP = 'identity';

    /** @return array<int, ReadinessProbe> */
    public function probes(): array
    {
        return [
            new ReadinessProbe('app_url', self::GROUP, $this->appUrl(...)),
            new ReadinessProbe('host_matches_app_url', self::GROUP, $this->hostMatches(...)),
            new ReadinessProbe('canonical_host', self::GROUP, $this->canonicalHost(...)),
            new ReadinessProbe('sequences', self::GROUP, $this->sequences(...)),
        ];
    }

    /** The address in .env and the address in the settings name the same host. */
    public function appUrl(ReadinessContext $c): C
    {
        $label = 'The site address is consistent';
        $host = $c->appHost();

        if ($host === '') {
            return $c->check('app_url', $label, self::GROUP, C::FAIL, 'APP_URL in .env is empty or not an address.', fix: 'Set APP_URL to the address this site is reached at.');
        }

        $stored = (string) DB::table('settings')->where('group', 'general')->where('key', 'site_url')->value('value');
        $storedHost = strtolower((string) (parse_url($stored, PHP_URL_HOST) ?: ''));

        if ($storedHost !== '' && $storedHost !== $host) {
            return $c->check('app_url', $label, self::GROUP, C::FAIL, 'APP_URL and the site address in the settings name different hosts.', ['env_host' => $host, 'settings_host' => $storedHost], 'Make the two the same (General settings, and APP_URL in .env).');
        }

        return $c->check('app_url', $label, self::GROUP, C::PASS, "The site address is {$host}.");
    }

    /** The host this request was made to is the one the site says it lives at (a site cloned onto another address still thinks it is the original). */
    public function hostMatches(ReadinessContext $c): C
    {
        $label = 'This request reached the site on its own address';

        if ($c->requestHost === null) {
            return $c->check('host_matches_app_url', $label, self::GROUP, C::SKIP, 'Checked when the report is run from a browser, where the address that was asked for is known.');
        }

        if (strtolower($c->requestHost) === $c->appHost()) {
            return $c->check('host_matches_app_url', $label, self::GROUP, C::PASS, 'The address asked for is the address of the site.');
        }

        $production = $c->safe->mode() === InstanceMode::Production;

        return $c->check('host_matches_app_url', $label, self::GROUP, $production ? C::FAIL : C::WARN, 'This request was made to a different address than APP_URL.'.($production ? ' A production site on a copied address is usually a clone that was never re-pointed.' : ''), ['asked_for' => strtolower($c->requestHost), 'app_url_host' => $c->appHost()], 'Open the site at its own address, or correct APP_URL.');
    }

    /** The canonical host is empty, or it is this site's own host: anything else sends every visitor to another site. */
    public function canonicalHost(ReadinessContext $c): C
    {
        $label = 'The canonical host points at this site';
        $stored = strtolower(trim((string) DB::table('settings')->where('group', 'seo')->where('key', 'canonical_host')->value('value')));

        if ($stored === '') {
            return $c->check('canonical_host', $label, self::GROUP, C::PASS, 'No canonical host is set: the site answers on whatever address it is given.');
        }

        return $stored === $c->appHost()
            ? $c->check('canonical_host', $label, self::GROUP, C::PASS, 'The canonical host is the site\'s own host.')
            : $c->check('canonical_host', $label, self::GROUP, C::FAIL, 'The canonical host is another host than this site: visitors would be sent there.', ['canonical' => $stored, 'site' => $c->appHost()], 'Empty the canonical host, or set it to this site\'s host (SEO settings).');
    }

    /** The next order, invoice or document number must not already exist: the database refuses a repeat, so the first order would fail. */
    public function sequences(ReadinessContext $c): C
    {
        $label = 'The next numbers are free';
        $behind = (new SequenceFloor)->behind();

        return $behind === []
            ? $c->check('sequences', $label, self::GROUP, C::PASS, 'Every counter is ahead of the numbers already issued.')
            : $c->check('sequences', $label, self::GROUP, C::FAIL, 'The counter of '.implode(', ', array_keys($behind)).' is behind numbers that already exist.', ['series' => $behind], 'Raise the counter, or run the import\'s numbering step again.');
    }
}
