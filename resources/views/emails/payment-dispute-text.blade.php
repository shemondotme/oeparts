PAYMENT DISPUTE — OeParts

@if($orderNumber)
A dispute was opened on order {{ $orderNumber }}.
@else
A dispute was opened on a payment.
@endif

Event: {{ $eventType }}
@if($status)
Status: {{ $status }}
@endif
@if($stage)
Stage: {{ $stage }}
@endif
@if($amount && $currency)
Disputed amount: {{ $amount }} {{ $currency }}
@endif
@if($disputeId)
Dispute ID: {{ $disputeId }}
@endif
@if($reason)
Reason: {{ $reason }}
@endif

No automatic action has been taken on this order or payment — log in to the admin panel > Orders
to review and respond. Gateway disputes carry a response deadline; missing it typically loses the
case automatically, so please review promptly.
