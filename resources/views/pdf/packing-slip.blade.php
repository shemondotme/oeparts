<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Packing slip {{ $order->order_number }}</title>
    @include('pdf.partials.invoice-styles')
</head>
<body>
    {{-- Warehouse copy in the same Band design as the invoice: no prices, no payment details.
         English-only like the invoice. --}}
    @php
        $shipLines = [
            $order->shipping_name,
            $order->shipping_address_line1,
            $order->shipping_address_line2,
            trim(implode(' ', array_filter([$order->shipping_postal_code, $order->shipping_city]))),
            $order->shipping_state,
            $order->shipping_country_code,
            filled($order->customer_phone) ? 'Phone: '.$order->customer_phone : null,
        ];
        $shipmentLines = [
            $order->shipping_method_name_snapshot ?: 'Shipment',
            filled($order->tracking_number) ? 'Tracking: '.$order->tracking_number : null,
            'Parts: '.$order->items->count().' · Pieces: '.$order->items->sum('quantity'),
        ];
        $meta = [
            'Order' => $order->order_number,
            'Date' => $order->created_at->format('d/m/Y'),
            'Shipping' => $order->shipping_method_name_snapshot,
            'Priority' => $order->urgent_processing ? 'URGENT' : null,
        ];
    @endphp

    @include('pdf.partials.doc-header', ['docTitle' => 'Packing slip', 'meta' => $meta])

    <div class="content">
        @include('pdf.partials.parties', ['parties' => [
            ['label' => 'Ship to', 'lines' => $shipLines],
            ['label' => 'Shipment', 'lines' => $shipmentLines],
        ]])

        @if($order->urgent_processing)
            <div class="notice-box"><strong>Urgent order</strong> — prioritised for same-day dispatch.</div>
        @endif

        <table class="items">
            <thead>
                <tr>
                    <th>OEM #</th>
                    <th>Part</th>
                    <th>Condition</th>
                    <th class="text-right">Qty</th>
                    <th style="text-align: center;">Packed</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                <tr>
                    <td class="mono">{{ $item->oem_number_snapshot ?? '—' }}</td>
                    <td>
                        @if($item->product)<strong>{{ trans_field($item->product->name) }}</strong>@endif
                        @if($item->manufacturer_snapshot)<div style="color: #7A7360;">{{ $item->manufacturer_snapshot }}</div>@endif
                    </td>
                    <td><span class="condition-tag">{{ $item->condition_snapshot ?? '—' }}</span></td>
                    <td class="text-right mono">{{ $item->quantity }}</td>
                    <td style="text-align: center;"><span style="display: inline-block; width: 12px; height: 12px; border: 1.5px solid #0A1228;">&nbsp;</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>

        @if(filled($order->customer_note))
            <div class="notice-box" style="margin-top: 18px;">
                <strong>Customer note</strong><br>
                {!! nl2br(e($order->customer_note)) !!}
            </div>
        @endif

        <table style="margin-top: 34px;">
            <tr>
                <td style="width: 47%; border-top: 1px solid #0A1228; padding-top: 4px;"><div class="label">Packed by</div></td>
                <td style="width: 6%;"></td>
                <td style="width: 47%; border-top: 1px solid #0A1228; padding-top: 4px;"><div class="label">Date</div></td>
            </tr>
        </table>

        <div class="footer">
            <div>{{ $settings['company_name'] }}@if(filled($settings['company_email'])) · {{ $settings['company_email'] }}@endif @if(filled($settings['company_phone'])) · {{ $settings['company_phone'] }}@endif</div>
            <div class="mono">Generated {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </div>
</body>
</html>
