<?php

namespace App\Services\Migration\Package\Exceptions;

use App\Services\Migration\MigrationErrorCode;

class TruncatedException extends PackageException
{
    public const DEFAULT_MESSAGE = 'The file ends before it should.';

    public function __construct(string $message = '', ?\Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : self::DEFAULT_MESSAGE, 0, $previous);
    }

    public function errorCode(): MigrationErrorCode
    {
        return MigrationErrorCode::PackageIncomplete;
    }
}
