<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Packing slip {{ $order->order_number }}</title>
    @include('pdf.partials.invoice-styles')
</head>
<body>
    {{-- Warehouse copy: no prices, no payment details. English-only like the invoice. --}}
    <table>
        <tr>
            <td>
                <div style="font-size: 20px; font-weight: bold;">PACKING SLIP</div>
                <div class="mono" style="font-size: 13px; margin-top: 4px;">{{ $order->order_number }}</div>
            </td>
            <td class="text-right">
                <div style="font-weight: bold;">{{ $company }}</div>
                <div>{{ $order->created_at->format('d M Y') }}</div>
                @if($order->urgent_processing)
                    <div style="font-weight: bold; margin-top: 4px;">URGENT — same-day dispatch</div>
                @endif
            </td>
        </tr>
    </table>

    <table style="margin-top: 22px;">
        <tr>
            <td style="width: 50%;">
                <div class="label">Ship to</div>
                <div style="margin-top: 4px;">
                    <strong>{{ $order->shipping_name }}</strong><br>
                    {{ $order->shipping_address_line1 }}<br>
                    @if($order->shipping_address_line2){{ $order->shipping_address_line2 }}<br>@endif
                    {{ trim($order->shipping_postal_code.' '.$order->shipping_city) }}<br>
                    @if($order->shipping_state){{ $order->shipping_state }}<br>@endif
                    {{ $order->shipping_country_code }}
                    @if($order->customer_phone)<br>Tel: {{ $order->customer_phone }}@endif
                </div>
            </td>
            <td style="width: 50%;">
                <div class="label">Shipment</div>
                <div style="margin-top: 4px;">
                    @if($order->shipping_method_name_snapshot)Method: {{ $order->shipping_method_name_snapshot }}<br>@endif
                    @if($order->tracking_number)Tracking: <span class="mono">{{ $order->tracking_number }}</span><br>@endif
                    Items: {{ $order->items->sum('quantity') }}
                </div>
            </td>
        </tr>
    </table>

    <table style="margin-top: 22px; border: 1px solid #D8CFB6;">
        <thead>
            <tr style="background: #EFE9D6;">
                <th align="left" style="padding: 7px 8px;">OEM number</th>
                <th align="left" style="padding: 7px 8px;">Part</th>
                <th align="left" style="padding: 7px 8px;">Condition</th>
                <th align="center" style="padding: 7px 8px;">Qty</th>
                <th align="center" style="padding: 7px 8px;">Packed</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr style="border-top: 1px solid #D8CFB6;">
                    <td class="mono" style="padding: 7px 8px;">{{ $item->oem_number_snapshot }}</td>
                    <td style="padding: 7px 8px;">
                        {{ $item->product ? trans_field($item->product->name) : '' }}
                        @if($item->manufacturer_snapshot)<br><span style="color: #4E5A74;">{{ $item->manufacturer_snapshot }}</span>@endif
                    </td>
                    <td style="padding: 7px 8px;">{{ $item->condition_snapshot }}</td>
                    <td align="center" class="mono" style="padding: 7px 8px;">{{ $item->quantity }}</td>
                    <td align="center" style="padding: 7px 8px;">[ &nbsp; ]</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if(filled($order->customer_note))
        <div style="margin-top: 22px;">
            <div class="label">Customer note</div>
            <div style="margin-top: 4px;">{!! nl2br(e($order->customer_note)) !!}</div>
        </div>
    @endif
</body>
</html>
