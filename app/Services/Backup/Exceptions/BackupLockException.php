<?php

namespace App\Services\Backup\Exceptions;

/**
 * Thrown when the shared update/backup lock is already held — a backup or an
 * update is already in progress on this instance.
 */
class BackupLockException extends BackupException
{
}
