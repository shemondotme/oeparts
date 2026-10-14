<?php

namespace App\Services\Migration\Import;

use App\Services\Migration\Import\Contracts\QueueGuard;

/** The queue guard until the real one lands: holds nothing, resumes nothing. */
final class NullQueueGuard implements QueueGuard
{
    public function hold(): void {}

    public function resume(): void {}
}
