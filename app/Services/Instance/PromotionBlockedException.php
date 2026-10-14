<?php

namespace App\Services\Instance;

/**
 * Releasing a site or taking a copy live was refused, and why. `reason` is a short code a command or a page can switch on;
 * `details` is what to show next to it (the failing checks, the tables that hold activity). Nothing was changed when this is thrown,
 * except for the one case that says otherwise in its message.
 */
final class PromotionBlockedException extends \RuntimeException
{
    /** @param  array<string, mixed>  $details */
    public function __construct(public readonly string $reason, string $message, public readonly array $details = [])
    {
        parent::__construct($message);
    }
}
