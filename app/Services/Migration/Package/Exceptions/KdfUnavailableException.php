<?php

namespace App\Services\Migration\Package\Exceptions;

use App\Services\Migration\MigrationErrorCode;

class KdfUnavailableException extends PackageException
{
    public const DEFAULT_MESSAGE = 'The key derivation this file needs is not available on this PHP.';

    public function __construct(string $message = '', ?\Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : self::DEFAULT_MESSAGE, 0, $previous);
    }

    public function errorCode(): MigrationErrorCode
    {
        return MigrationErrorCode::KdfUnavailable;
    }
}
