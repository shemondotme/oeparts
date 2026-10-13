<?php

namespace App\Services\Migration\Package\Exceptions;

use App\Services\Migration\MigrationErrorCode;

class InsufficientResourcesException extends PackageException
{
    public const DEFAULT_MESSAGE = 'This server has too little memory to open the file.';

    public function __construct(string $message = '', private readonly int $neededMb = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : self::DEFAULT_MESSAGE, 0, $previous);
    }

    public function params(): array
    {
        return ['mb' => $this->neededMb];
    }

    public function errorCode(): MigrationErrorCode
    {
        return MigrationErrorCode::KdfMemory;
    }
}
