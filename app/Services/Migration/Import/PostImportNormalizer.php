<?php

namespace App\Services\Migration\Import;

use App\Services\Instance\CanonicalHostPolicy;
use App\Services\Instance\InstanceState;

/**
 * The few settings that must say something different on the site the package was moved INTO, applied after the domain rewrite
 * so that these explicit values win over anything the rewrite left:
 *
 *   general.site_url         the new address;
 *   seo.canonical_host       decided by CanonicalHostPolicy (the old value is remembered in the ledger for the day a copy goes live);
 *   seo.gsc_property_url     emptied: it names the old site's Search Console property;
 *   maintenance.enabled      off: a freeze export arrives with the shop in maintenance;
 *   security.otp_enabled     off when the mail server's password did not travel (nobody could receive a code and sign in).
 *
 * It returns what it changed, as "setting => what was done" (never a value), for the report's "we adjusted these for you".
 */
final class PostImportNormalizer
{
    public function __construct(
        private readonly ImportSettings $settings,
        private readonly CanonicalHostPolicy $canonical,
        private readonly InstanceState $ledger,
    ) {}

    /** @return array<string, string> */
    public function apply(ImportState $state, ImportTarget $target): array
    {
        $done = [];

        if ($this->settings->put('general', 'site_url', $target->url)) {
            $done['general.site_url'] = 'set to the new address';
        }

        $sourceCanonical = $this->sourceCanonical($state);
        $canonical = $this->canonical->resolve($target->launch, $sourceCanonical, $target->host);

        if ($this->settings->put('seo', 'canonical_host', $canonical)) {
            $done['seo.canonical_host'] = $canonical === '' ? 'emptied (the new site answers on any address it is given)' : 'set to the new host';
        }

        $this->rememberSourceCanonical($state, $sourceCanonical);

        if ($this->settings->stored('seo', 'gsc_property_url') !== null && $this->settings->stored('seo', 'gsc_property_url') !== '') {
            $this->settings->put('seo', 'gsc_property_url', '');
            $done['seo.gsc_property_url'] = 'emptied (it belongs to the old site)';
        }

        if ($this->settings->stored('maintenance', 'enabled') === '1' && $this->settings->put('maintenance', 'enabled', '0', 'boolean')) {
            $done['maintenance.enabled'] = 'switched off (the package was taken while the shop was in maintenance)';
        }

        if (! $this->mailSecretsTravelled($state) && $this->settings->stored('security', 'otp_enabled') === '1' && $this->settings->put('security', 'otp_enabled', '0', 'boolean')) {
            $done['security.otp_enabled'] = 'switched off (the mail password did not travel, so no sign-in code could be sent)';
        }

        return $done;
    }

    /**
     * The canonical host of the SOURCE: what the settings table held when the rewrite began. The rewrite leaves this key alone,
     * so on a first run it is still the imported value; a resumed run reads it from the state, where the first run wrote it.
     */
    private function sourceCanonical(ImportState $state): string
    {
        if (array_key_exists('source_canonical_host', $state->package)) {
            return (string) $state->package['source_canonical_host'];
        }

        return (string) ($this->settings->stored('seo', 'canonical_host') ?? '');
    }

    private function rememberSourceCanonical(ImportState $state, string $sourceCanonical): void
    {
        $state->package['source_canonical_host'] = $sourceCanonical;

        $this->ledger->update(function (array $ledger) use ($sourceCanonical): array {
            $ledger['import'] = ($ledger['import'] ?? []) + ['source_canonical_host' => $sourceCanonical];
            $ledger['import']['source_canonical_host'] = $sourceCanonical;

            return $ledger;
        });
    }

    private function mailSecretsTravelled(ImportState $state): bool
    {
        return ($state->package['export']['secrets_included'] ?? false) === true && ($state->options['include_secrets'] ?? false) === true;
    }
}
