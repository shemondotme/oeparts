@extends('emails.layout')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding: 32px;">
                <p style="margin: 0 0 4px; font-size: 11px; letter-spacing: 0.18em; text-transform: uppercase; color: #B91C1C; font-weight: 700;">
                    Payment Dispute
                </p>

                <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1228; font-weight: 800;">
                    @if($orderNumber)
                        A dispute was opened on order {{ $orderNumber }}
                    @else
                        A dispute was opened on a payment
                    @endif
                </h1>

                <p style="margin: 0 0 20px; padding: 12px 16px; background: #FEF2F2; border: 1px solid #FCA5A5; border-radius: 8px; font-size: 14px; color: #7F1D1D;">
                    {{ $eventType }}
                    @if($status) &middot; status: {{ $status }} @endif
                    @if($stage) &middot; stage: {{ $stage }} @endif
                </p>

                @if($amount && $currency)
                    <p style="margin: 0 0 8px; font-size: 14px; color: #555;">
                        Disputed amount: <strong>{{ $amount }} {{ $currency }}</strong>
                    </p>
                @endif
                @if($disputeId)
                    <p style="margin: 0 0 8px; font-size: 14px; color: #555;">
                        Dispute ID: <strong style="font-family: monospace;">{{ $disputeId }}</strong>
                    </p>
                @endif
                @if($reason)
                    <p style="margin: 0 0 20px; font-size: 14px; color: #555;">
                        Reason: <strong>{{ $reason }}</strong>
                    </p>
                @endif

                <p style="margin: 0; font-size: 13px; line-height: 1.6; color: #777;">
                    No automatic action has been taken on this order or payment — log in to the admin panel
                    &rarr; <strong>Orders</strong> to review and respond. Gateway disputes carry a response
                    deadline; missing it typically loses the case automatically, so please review promptly.
                </p>
            </td>
        </tr>
    </table>
@endsection
