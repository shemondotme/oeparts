<?php

namespace App\Services\Migration\Package\Exceptions;

use App\Services\Migration\MigrationErrorCode;

class TextModeTransferException extends PackageException
{
    public const DEFAULT_MESSAGE = 'The file was changed by a text-mode transfer (line endings): upload it again in BINARY mode.';

    public function __construct(string $message = '', ?\Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : self::DEFAULT_MESSAGE, 0, $previous);
    }

    public function errorCode(): MigrationErrorCode
    {
        return MigrationErrorCode::PackageTextMode;
    }
}
