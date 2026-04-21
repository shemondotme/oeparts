<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Packing Slip — Order #{{ $order->order_number }}</title>
    <style>
        @import url('https://fonts.bunny.net/css?family=jetbrains-mono:400,600');
        body { font-family: Arial, sans-serif; font-size: 13px; color: #1e293b; margin: 0; padding: 24px; }
        .mono { font-family: 'JetBrains Mono', 'Courier New', monospace; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0B3A68; padding-bottom: 16px; margin-bottom: 24px; }
        .company { font-size: 22px; font-weight: bold; color: #0B3A68; }
        .company-sub { font-size: 11px; color: #64748b; margin-top: 2px; }
        .meta { text-align: right; }
        .meta h2 { font-size: 18px; color: #0B3A68; margin: 0 0 4px; }
        .meta p { margin: 2px 0; font-size: 12px; color: #475569; }
        .section { margin-bottom: 20px; }
        .section-title { font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 6px; }
        .address { line-height: 1.6; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #f1f5f9; text-align: left; padding: 8px 10px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; border-bottom: 1px solid #cbd5e1; }
        td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        .qty-col { width: 60px; text-align: center; }
        .print-btn { display: inline-block; margin: 24px 0; padding: 8px 20px; background: #0B3A68; color: #fff; border: none; border-radius: 6px; font-size: 13px; cursor: pointer; }
        @media print {
            @page { size: A4; margin: 1cm; }
            .print-btn { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

    <div class="header">
        <div>
            <div class="company">OEMHub</div>
            <div class="company-sub">OEM Parts &amp; Accessories</div>
        </div>
        <div class="meta">
            <h2>Packing Slip</h2>
            <p><strong>Order #:</strong> {{ $order->order_number }}</p>
            <p><strong>Date:</strong> {{ $order->created_at->format('d M Y') }}</p>
            @if($order->shippingMethod)
                <p><strong>Shipping:</strong> {{ $order->shippingMethod->name }}</p>
            @endif
        </div>
    </div>

    <div style="display: flex; gap: 40px; margin-bottom: 24px;">
        {{-- Ship To --}}
        <div class="section" style="flex:1">
            <div class="section-title">Ship To</div>
            <div class="address">
                <strong>{{ $order->shipping_name ?? ($order->user?->name ?? 'N/A') }}</strong><br>
                @if($order->shipping_address_line1)
                    {{ $order->shipping_address_line1 }}<br>
                    @if($order->shipping_address_line2)
                        {{ $order->shipping_address_line2 }}<br>
                    @endif
                    {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_postcode }}<br>
                    {{ $order->shipping_country }}
                @else
                    {{ $order->shipping_address }}
                @endif
            </div>
        </div>

        {{-- Customer Info --}}
        <div class="section" style="flex:1">
            <div class="section-title">Customer</div>
            <div class="address">
                @if($order->user)
                    {{ $order->user->email }}
                @else
                    {{ $order->guest_email }}
                @endif
                @if($order->phone)
                    <br>{{ $order->phone }}
                @endif
            </div>
        </div>
    </div>

    {{-- Items table --}}
    <div class="section">
        <div class="section-title">Items</div>
        <table>
            <thead>
                <tr>
                    <th>OEM Number</th>
                    <th>Description</th>
                    <th class="qty-col">Qty</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td class="mono">{{ $item->product->oem_number ?? '—' }}</td>
                        <td>
                            {{ $item->product_name_snapshot ?? ($item->product->name ?? '—') }}
                            @if($item->condition_snapshot)
                                <span style="font-size:11px; color:#64748b;"> ({{ $item->condition_snapshot }})</span>
                            @endif
                        </td>
                        <td class="qty-col">{{ $item->quantity }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <button class="print-btn" onclick="window.print()">Print Packing Slip</button>

</body>
</html>
