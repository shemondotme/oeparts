@extends('emails.layout')

@section('content')
    {{-- ══════════════════════════════════════════════════════════════════════
         ORDER STATUS UPDATE — INDUSTRIAL BLUEPRINT NOTIFICATION
         Focus: Clear status indication, timeline context, next steps.
         ══════════════════════════════════════════════════════════════════ --}}

    @php
        // The sentence says what THIS status means for the customer (and, for a
        // cancelled order that was already paid, that a refund is coming)
        // rather than one generic line for every transition.
        $bodyKey = 'emails.order_status_update.body_'.$newStatus->value;
        if ($newStatus === \App\Enums\OrderStatus::Cancelled && $order->payment_status === \App\Enums\PaymentStatus::Paid) {
            $bodyKey = 'emails.order_status_update.body_cancelled_paid';
        }
        $bodyLine = trans()->has($bodyKey, $locale)
            ? trans($bodyKey, ['order_number' => $order->order_number], $locale)
            : trans('emails.order_status_update.body', [], $locale);
    @endphp

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">

        {{-- ═══ DOC HEADER: Status Update ═══ --}}
        <tr>
            <td style="padding-bottom: 24px; border-bottom: 1px solid #D8CFB6;">
                <p class="spec-label" style="margin: 0 0 8px 0; color: #9A5A00;">
                    {{ mb_strtoupper(trans('emails.order_status_update.eyebrow', [], $locale)) }}
                </p>
                <h2 class="font-display" style="margin: 0; font-size: 24px; line-height: 32px; color: #0A1228;">
                    {{ trans('emails.order_status_update.order_heading', ['number' => $order->order_number], $locale) }}<span class="text-amber">.</span>
                </h2>
                <p style="margin: 12px 0 0 0; font-size: 15px; line-height: 22px; color: #4E5A74;">
                    {{ trans('emails.order_status_update.greeting', ['name' => $order->shipping_name], $locale) }}
                    <br>
                    {!! email_text($bodyLine) !!}
                </p>
            </td>
        </tr>

        {{-- ═══ STATUS CHIP & DETAILS ═══ --}}
        <tr>
            <td style="padding: 24px 0;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border: 1px solid #D8CFB6; background-color: #F7F3E7;">
                    <tr>
                        <td style="padding: 20px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                {{-- Current Status Row --}}
                                <tr>
                                    <td style="padding-bottom: 16px; border-bottom: 1px dashed #D8CFB6;">
                                        <span class="spec-label" style="color: #4E5A74; display: block; margin-bottom: 8px;">{{ mb_strtoupper(trans('emails.order_status_update.current_status', [], $locale)) }}</span>

                                        {{-- Dynamic Status Chip based on status string --}}
                                        @php
                                            // The status this email announces, not whatever the
                                            // order has moved on to by the time a queued mail is sent.
                                            $status = strtolower($newStatus->value);
                                            $chipBg = '#F1F5F9'; // default gray
                                            $chipText = '#64748B';

                                            if (str_contains($status, 'processing')) {
                                                $chipBg = '#DBEAFE'; $chipText = '#1D4ED8'; // Blue
                                            } elseif (str_contains($status, 'shipped') || str_contains($status, 'dispatched')) {
                                                $chipBg = '#FEF3C7'; $chipText = '#D97706'; // Amber
                                            } elseif (str_contains($status, 'delivered') || str_contains($status, 'completed')) {
                                                $chipBg = '#DCFCE7'; $chipText = '#166534'; // Green
                                            } elseif (str_contains($status, 'cancelled') || str_contains($status, 'failed')) {
                                                $chipBg = '#FEE2E2'; $chipText = '#DC2626'; // Red
                                            }
                                        @endphp

                                        <span style="display: inline-block; padding: 6px 12px; background-color: {{ $chipBg }}; color: {{ $chipText }}; font-family: 'Courier New', Courier, monospace; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1em; border-radius: 2px;">
                                            {{ mb_strtoupper(trans('emails.order_status_update.status.'.$newStatus->value, [], $locale)) }}
                                        </span>
                                    </td>
                                </tr>

                                {{-- Tracking (shipped) --}}
                                @if($newStatus === \App\Enums\OrderStatus::Shipped && filled($order->tracking_number))
                                <tr>
                                    <td style="padding-top: 16px;">
                                        <span class="spec-label" style="color: #4E5A74;">{{ mb_strtoupper(trans('emails.order_shipped.tracking_number', [], $locale)) }}</span>
                                        <span class="font-mono" style="display: block; margin-top: 4px; font-size: 14px; color: #0A1228; font-weight: bold;">{{ $order->tracking_number }}</span>
                                        @if(filled($order->tracking_url))
                                            <a href="{{ $order->tracking_url }}" style="display: inline-block; margin-top: 6px; font-size: 13px; color: #9A5A00; font-weight: bold;">{{ trans('emails.order_shipped.track_package', [], $locale) }} →</a>
                                        @endif
                                    </td>
                                </tr>
                                @endif

                                {{-- Timestamp --}}
                                <tr>
                                    <td style="padding-top: 16px;">
                                        <span class="spec-label" style="color: #4E5A74;">{{ mb_strtoupper(trans('emails.order_status_update.updated_at', [], $locale)) }}</span>
                                        <span class="font-mono" style="display: block; margin-top: 4px; font-size: 14px; color: #0A1228;">
                                            {{ $order->updated_at->format('d M Y, H:i T') }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ═══ CONTEXT / MESSAGE ═══ --}}
        @if(filled($supportMessage ?? null))
        <tr>
            <td style="padding-bottom: 24px;">
                <p class="spec-label" style="margin: 0 0 8px 0; color: #9A5A00;">
                    {{ mb_strtoupper(trans('emails.order_status_update.support_note', [], $locale)) }}
                </p>
                <div style="background-color: #FFFFFF; border-left: 4px solid #F59E0B; padding: 16px; font-size: 14px; line-height: 22px; color: #0A1228;">
                    {!! nl2br(e($supportMessage)) !!}
                </div>
            </td>
        </tr>
        @endif

        {{-- ═══ CTA BUTTON ═══ --}}
        <tr>
            <td align="center" style="padding: 24px 0; border-top: 1px solid #D8CFB6;">
                <p style="margin: 0 0 20px 0; font-size: 14px; line-height: 20px; color: #4E5A74;">
                    {{ trans('emails.order_status_update.view_hint', [], $locale) }}
                </p>
                <a href="{{ route('frontend.account.order.detail', ['lang' => $locale, 'order' => $order->id]) }}"
                   class="btn-primary"
                   style="display: inline-block; padding: 13px 26px; background-color: #F59E0B; color: #0A1228 !important; text-decoration: none; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1em; border: 1px solid #F59E0B;">
                    {{ mb_strtoupper(trans('emails.order_status_update.view_button', [], $locale)) }} →
                </a>
            </td>
        </tr>

    </table>
@endsection
