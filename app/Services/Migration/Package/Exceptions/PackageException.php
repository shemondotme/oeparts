<?php

namespace App\Services\Migration\Package\Exceptions;

use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;

/**
 * Base of everything the .oeparts reader and writer can refuse. Each subclass knows which
 * catalogue code explains it to a person; toMigrationException() carries it up to the layers
 * that show text, so no technical wording ever reaches the interface.
 */
abstract class PackageException extends \RuntimeException
{
    /** The catalogue code a person is shown for this refusal. */
    abstract public function errorCode(): MigrationErrorCode;

    /** @return array<string, scalar|null> values the code's text interpolates */
    public function params(): array
    {
        return [];
    }

    public function toMigrationException(): MigrationException
    {
        return new MigrationException($this->errorCode(), $this->params(), $this);
    }
}
