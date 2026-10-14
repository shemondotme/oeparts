<?php

namespace App\Services\Instance;

use App\Services\Instance\Contracts\InstanceBegin;
use App\Services\Migration\Import\ImportState;

/** The instance `begin()` until the real one lands: writes no .env key and changes no mode. */
final class NullInstanceBegin implements InstanceBegin
{
    public function begin(ImportState $state): void {}
}
