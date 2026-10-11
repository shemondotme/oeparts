<?php

namespace App\Services\Cleanup\Exceptions;

/**
 * Raised when a cleanup cannot start because something else owns the system right now:
 * another cleanup, or a backup / update / restore. Always safe to retry later.
 */
class CleanupBusyException extends CleanupException
{
    public function __construct(string $message, public readonly ?int $activeRunId = null)
    {
        parent::__construct($message);
    }
}
