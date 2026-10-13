<?php

namespace App\Services\Migration\Package\Exceptions;

use App\Services\Migration\MigrationErrorCode;

class CorruptException extends PackageException
{
    public const DEFAULT_MESSAGE = 'A part of the file is damaged.';

    public function __construct(string $message = '', private readonly string $part = 'unknown', ?\Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : self::DEFAULT_MESSAGE, 0, $previous);
    }

    public function params(): array
    {
        return ['part' => $this->part];
    }

    public function errorCode(): MigrationErrorCode
    {
        return MigrationErrorCode::PackageCorrupt;
    }
}
