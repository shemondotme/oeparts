<?php

namespace App\Services\Migration;

/**
 * The one exception the migration services throw. It carries a catalogue code and the values
 * that code's text interpolates (:part, :seconds, ...). getMessage() is for the log only; the
 * interface shows the translated text of the code.
 */
class MigrationException extends \RuntimeException
{
    /** @param  array<string, scalar|null>  $params */
    public function __construct(
        public readonly MigrationErrorCode $errorCode,
        public readonly array $params = [],
        ?\Throwable $previous = null,
    ) {
        $detail = $params === [] ? '' : ' '.json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        parent::__construct('Migration error '.$errorCode->value.$detail, 0, $previous);
    }
}
