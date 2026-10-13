<?php

namespace App\Services\Migration\Groups;

/**
 * The four ready-made selections. "Full site" is FullLive; "choose myself" is any other set of groups.
 */
enum Preset: string
{
    case FullLive = 'full_live';
    case Staging = 'staging';
    case NewStoreSameCatalog = 'new_store_same_catalog';
    case CatalogOnly = 'catalog_only';

    public function labelKey(): string
    {
        return 'migration.preset.'.$this->value.'.label';
    }

    public function descriptionKey(): string
    {
        return 'migration.preset.'.$this->value.'.description';
    }

    /**
     * @return array<int, GroupKey> the groups this preset selects (before dependencies are resolved)
     */
    public function groups(): array
    {
        return match ($this) {
            self::FullLive => $this->everythingExcept(GroupKey::Logs, GroupKey::Carts),
            self::Staging => $this->everythingExcept(GroupKey::Logs, GroupKey::Carts, GroupKey::SalesFiles, GroupKey::Invoicing),
            self::NewStoreSameCatalog => [
                GroupKey::Core, GroupKey::Catalog, GroupKey::CatalogFiles, GroupKey::Media, GroupKey::Content,
                GroupKey::ShippingTax, GroupKey::AdminPrefs,
            ],
            self::CatalogOnly => [GroupKey::Core, GroupKey::Catalog, GroupKey::CatalogFiles, GroupKey::Media],
        };
    }

    /** Personal data is scrambled in the copy (export time). */
    public function anonymises(): bool
    {
        return $this === self::Staging;
    }

    /** Invoice and order numbering starts again in the copy, so a new shop does not continue the old one's numbers. */
    public function resetsSequences(): bool
    {
        return $this === self::NewStoreSameCatalog || $this === self::CatalogOnly;
    }

    /** Secrets (passwords, API keys) are always left out of a preset; a person can opt in only for the full copy. */
    public function excludesSecretsByDefault(): bool
    {
        return true;
    }

    /** A preset that scrambles data can never carry secrets. */
    public function forcesSecretsOut(): bool
    {
        return $this === self::Staging;
    }

    /** @return array<int, GroupKey> */
    private function everythingExcept(GroupKey ...$excluded): array
    {
        return array_values(array_filter(
            GroupKey::cases(),
            static fn (GroupKey $k): bool => $k !== GroupKey::System && ! in_array($k, $excluded, true)
        ));
    }
}
