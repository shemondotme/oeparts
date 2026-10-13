<?php

namespace App\Services\Migration\Export\Transform;

/**
 * The exported copy of `maintenance.enabled` is always 0. A freeze export runs while the shop shows the
 * maintenance page, so the live value is 1; copied as it is, the new site would be born in maintenance
 * mode and a person who forgot the setting would think the import failed.
 */
final class MaintenanceNeutraliser implements RowTransformer
{
    public function tables(): array
    {
        return ['settings'];
    }

    public function transform(string $table, array $row): array
    {
        if (($row['group'] ?? null) === 'maintenance' && ($row['key'] ?? null) === 'enabled') {
            $row['value'] = '0';
        }

        return $row;
    }
}
