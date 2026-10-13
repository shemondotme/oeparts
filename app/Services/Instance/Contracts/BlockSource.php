<?php

namespace App\Services\Instance\Contracts;

use App\Services\Instance\BlockLevel;

/**
 * Something that can hold the installation still: an export freeze, an import in progress, a staging or
 * quarantine instance mode. It registers itself with the SystemGate and answers, cheaply and without
 * touching the database where it can, how much it is holding right now.
 */
interface BlockSource
{
    public function level(): BlockLevel;

    /** A short phrase for the admin screens and logs: "an export freeze is active". */
    public function reason(): string;
}
