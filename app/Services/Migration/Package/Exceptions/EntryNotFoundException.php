<?php

namespace App\Services\Migration\Package\Exceptions;

use App\Services\Migration\MigrationErrorCode;

class EntryNotFoundException extends PackageException
{
    public const DEFAULT_MESSAGE = 'The requested entry is not in the file.';

    public function __construct(string $message = '', ?\Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : self::DEFAULT_MESSAGE, 0, $previous);
    }

    public function errorCode(): MigrationErrorCode
    {
        return MigrationErrorCode::PackageCorrupt;
    }
}
