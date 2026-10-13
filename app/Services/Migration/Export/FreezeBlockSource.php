<?php

namespace App\Services\Migration\Export;

use App\Services\Instance\BlockLevel;
use App\Services\Instance\Contracts\BlockSource;

/**
 * The export freeze as the SystemGate sees it: a few bytes read from one small file, no database and no
 * dependencies, so registering it at boot costs nothing on a site that never exports.
 */
final class FreezeBlockSource implements BlockSource
{
    public function level(): BlockLevel
    {
        if (! is_file(FreezeState::file()->path())) {
            return BlockLevel::None;
        }

        $state = FreezeState::read();

        return $state !== null && $state->isLive() ? BlockLevel::Everything : BlockLevel::None;
    }

    public function reason(): string
    {
        return 'an export is holding the site still';
    }
}
