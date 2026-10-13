<?php

namespace App\Services\Backup;

/** What BackupLock::ensureHeld() found, and did, for an owner that wants to carry on working. */
enum LockCustody: string
{
    /** The lock file is still ours. */
    case Held = 'held';

    /** Nobody held it any more (a janitor released it, or it was never written) and we took it again. */
    case Reacquired = 'reacquired';

    /** Someone else holds it now: stop and write nothing more. */
    case Lost = 'lost';
}
