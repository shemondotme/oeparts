<?php

namespace App\Services\Migration\Package\Exceptions;

use App\Services\Migration\MigrationErrorCode;

/** The keys held for a package do not open the file that is there now: it is a different or altered file. */
class PackageChangedException extends PackageException
{
    public const DEFAULT_MESSAGE = 'The package is not the one these keys were made for.';

    public function __construct(string $message = '', ?\Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : self::DEFAULT_MESSAGE, 0, $previous);
    }

    public function errorCode(): MigrationErrorCode
    {
        return MigrationErrorCode::PackageChanged;
    }
}
