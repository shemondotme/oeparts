<?php

namespace App\Services\Migration\Export;

/**
 * The two disks whose folders an export may read, as AllowlistWalker and the file stage need them: the
 * absolute folder, and the path of that folder relative to the application root (which is what
 * PackagePathPolicy judges). Nothing else is ever readable by an export.
 */
final class ExportDisks
{
    /** @return array<string, array{path: string, prefix: string}> */
    public static function application(): array
    {
        return [
            'public' => ['path' => storage_path('app/public'), 'prefix' => 'storage/app/public'],
            'local' => ['path' => storage_path('app/private'), 'prefix' => 'storage/app/private'],
        ];
    }
}
