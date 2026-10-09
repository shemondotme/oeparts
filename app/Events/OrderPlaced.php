<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPlaced
{
    use Dispatchable, SerializesModels;

    /**
     * @param  bool  $attachInvoice  Attach the invoice PDF to the confirmation email
     *                               (admin-created orders; storefront orders never do).
     */
    public function __construct(
        public readonly Order $order,
        public readonly bool $attachInvoice = false,
    ) {}
}
