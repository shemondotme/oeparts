<?php

namespace App\Services\Migration\Package\Exceptions;

use App\Services\Migration\MigrationErrorCode;

class NotAPackageException extends PackageException
{
    public const DEFAULT_MESSAGE = 'The file does not start with the OEPARTS1 signature.';

    public function __construct(string $message = '', ?\Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : self::DEFAULT_MESSAGE, 0, $previous);
    }

    public function errorCode(): MigrationErrorCode
    {
        return MigrationErrorCode::PackageNotOeparts;
    }
}
