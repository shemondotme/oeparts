<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PartInquiryReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $productId,
        public readonly string $oemNumber,
        public readonly string $customerEmail,
        public readonly ?string $message = null
    ) {}
}
