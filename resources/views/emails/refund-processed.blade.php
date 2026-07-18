@extends('emails.layout')

@section('content')
    {{-- ══════════════════════════════════════════════════════════════════════
         REFUND PROCESSED — INDUSTRIAL BLUEPRINT FINANCIAL DOCUMENT
         Focus: Clarity, precision, financial breakdown.
         ══════════════════════════════════════════════════════════════════ --}}

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">

        {{-- ═══ DOC HEADER: Refund Notification ═══ --}}
        <tr>
            <td style="padding-bottom: 24px; border-bottom: 1px solid #D8CFB6;">
                <p class="spec-label" style="margin: 0 0 8px 0; color: #9A5A00;">
                    FINANCE · REFUND ISSUED
                </p>
                <h2 class="font-display" style="margin: 0; font-size: 24px; line-height: 32px; color: #0A1228;">
                    Your refund has been processed<span class="text-amber">.</span>
                </h2>
                <p style="margin: 12px 0 0 0; font-size: 15px; line-height: 22px; color: #4E5A74;">
                    {{ trans('emails.refund_processed.greeting', ['name' => $refund->user->name ?? 'Customer'], $locale) }}
                    <br>
                    {{ trans('emails.refund_processed.body', ['order_number' => $refund->order->order_number], $locale) }}
                </p>
            </td>
        </tr>

        {{-- ═══ REFUND SUMMARY CARD ═══ --}}
        <tr>
            <td style="padding: 24px 0;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border: 1px solid #D8CFB6; background-color: #F7F3E7;">
                    <tr>
                        <td style="padding: 20px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                {{-- Refund ID --}}
                                <tr>
                                    <td style="padding-bottom: 12px;">
                                        <span class="spec-label" style="color: #4E5A74;">REFUND ID</span>
                                    </td>
                                    <td align="right" style="padding-bottom: 12px;">
                                        <span class="font-mono" style="font-size: 14px; color: #0A1228; font-weight: bold;">
                                            {{ $refund->id }}
                                        </span>
                                    </td>
                                </tr>
                                {{-- Original Order --}}
                                <tr>
                                    <td style="padding-bottom: 12px;">
                                        <span class="spec-label" style="color: #4E5A74;">ORIGINAL ORDER</span>
                                    </td>
                                    <td align="right" style="padding-bottom: 12px;">
                                        <span class="font-mono" style="font-size: 14px; color: #0A1228;">
                                            {{ $refund->order->order_number }}
                                        </span>
                                    </td>
                                </tr>
                                {{-- Date Processed --}}
                                <tr>
                                    <td style="padding-bottom: 12px; border-bottom: 1px dashed #D8CFB6;">
                                        <span class="spec-label" style="color: #4E5A74;">DATE PROCESSED</span>
                                    </td>
                                    <td align="right" style="padding-bottom: 12px; border-bottom: 1px dashed #D8CFB6;">
                                        <span class="font-mono" style="font-size: 14px; color: #0A1228;">
                                            {{ $refund->created_at->format('d M Y') }}
                                        </span>
                                    </td>
                                </tr>
                                {{-- Reason --}}
                                <tr>
                                    <td colspan="2" style="padding-top: 12px;">
                                        <span class="spec-label" style="color: #4E5A74; display: block; margin-bottom: 4px;">REASON</span>
                                        <p style="margin: 0; font-size: 14px; line-height: 20px; color: #0A1228;">
                                            {{ $refund->reason ?? 'Customer request / Return' }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ═══ FINANCIAL BREAKDOWN ═══ --}}
        <tr>
            <td style="padding-bottom: 24px;">
                <p class="spec-label" style="margin: 0 0 12px 0; color: #9A5A00;">
                    REFUND AMOUNT
                </p>

                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td width="50%"></td>
                        <td width="50%">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                {{-- Subtotal Refunded --}}
                                <tr>
                                    <td style="padding: 6px 0; border-bottom: 1px dotted #D8CFB6;">
                                        <span style="font-size: 14px; color: #4E5A74;">Items Refunded</span>
                                    </td>
                                    <td align="right" style="padding: 6px 0; border-bottom: 1px dotted #D8CFB6;">
                                        <span class="font-mono" style="font-size: 14px; color: #0A1228;">{{ number_format($refund->amount, 2) }} €</span>
                                    </td>
                                </tr>

                                {{-- Shipping Refund (if any) --}}
                                @if($refund->shipping_refund > 0)
                                    <tr>
                                        <td style="padding: 6px 0; border-bottom: 1px dotted #D8CFB6;">
                                            <span style="font-size: 14px; color: #4E5A74;">Shipping Refund</span>
                                        </td>
                                        <td align="right" style="padding: 6px 0; border-bottom: 1px dotted #D8CFB6;">
                                            <span class="font-mono" style="font-size: 14px; color: #0A1228;">{{ number_format($refund->shipping_refund, 2) }} €</span>
                                        </td>
                                    </tr>
                                @endif

                                {{-- Total Refund --}}
                                <tr>
                                    <td style="padding: 12px 0;">
                                        <span class="spec-label" style="color: #0A1228;">TOTAL REFUND</span>
                                    </td>
                                    <td align="right" style="padding: 12px 0;">
                                        <span class="font-mono" style="font-size: 18px; color: #0A1228; font-weight: bold;">
                                            {{ number_format($refund->total_refund_amount, 2) }} €
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ═══ TIMELINE / EXPECTATION ═══ --}}
        <tr>
            <td style="padding-bottom: 24px; border-bottom: 1px solid #D8CFB6;">
                <p class="spec-label" style="margin: 0 0 8px 0; color: #9A5A00;">
                    PROCESSING TIME
                </p>
                <p style="margin: 0 0 12px 0; font-size: 14px; line-height: 20px; color: #0A1228;">
                    {{ trans('emails.refund_processed.processing_time', ['days' => '5–10'], $locale) }}
                </p>
                <p style="margin: 0; font-size: 14px; line-height: 20px; color: #4E5A74;">
                    {{ settings('email.refund_notification', 'You will receive a separate notification once the funds have cleared.') }}
                </p>
            </td>
        </tr>

        {{-- ═══ CTA BUTTON ═══ --}}
        <tr>
            <td align="center" style="padding: 24px 0;">
                <p style="margin: 0 0 20px 0; font-size: 14px; line-height: 20px; color: #4E5A74;">
                    View the full details of this refund in your account.
                </p>
                <a href="{{ route('frontend.account.order.detail', ['lang' => $locale, 'order' => $refund->order_id]) }}"
                   class="btn-primary"
                   style="display: inline-block; padding: 14px 28px; background-color: #0A1228; color: #F7F3E7 !important; text-decoration: none; font-family: 'Courier New', Courier, monospace; font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.18em; border: 1px solid #0A1228;">
                    VIEW REFUND DETAILS →
                </a>
            </td>
        </tr>

    </table>
@endsection
