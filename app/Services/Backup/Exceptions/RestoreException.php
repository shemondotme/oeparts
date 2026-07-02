<?php

namespace App\Services\Backup\Exceptions;

/** Thrown when a restore cannot proceed safely (integrity, version, or missing part). */
class RestoreException extends BackupException
{
}
