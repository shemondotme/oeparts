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
                    {{ mb_strtoupper(trans('emails.refund_processed.eyebrow', [], $locale)) }}
                </p>
                <h2 class="font-display" style="margin: 0; font-size: 24px; line-height: 32px; color: #0A1228;">
                    {{ trans('emails.refund_processed.headline', [], $locale) }}<span class="text-amber">.</span>
                </h2>
                <p style="margin: 12px 0 0 0; font-size: 15px; line-height: 22px; color: #4E5A74;">
                    {{ trans('emails.refund_processed.greeting', ['name' => $refund->user->name ?? trans('emails.customer_fallback', [], $locale)], $locale) }}
                    <br>
                    {!! email_text(trans('emails.refund_processed.body', ['order_number' => $refund->order->order_number], $locale)) !!}
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
                                        <span class="spec-label" style="color: #4E5A74;">{{ mb_strtoupper(trans('emails.refund_processed.refund_id', [], $locale)) }}</span>
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
                                        <span class="spec-label" style="color: #4E5A74;">{{ mb_strtoupper(trans('emails.refund_processed.original_order', [], $locale)) }}</span>
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
                                        <span class="spec-label" style="color: #4E5A74;">{{ mb_strtoupper(trans('emails.refund_processed.date_processed', [], $locale)) }}</span>
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
                                        <span class="spec-label" style="color: #4E5A74; display: block; margin-bottom: 4px;">{{ mb_strtoupper(trans('emails.refund_processed.reason', [], $locale)) }}</span>
                                        <p style="margin: 0; font-size: 14px; line-height: 20px; color: #0A1228;">
                                            {{ $refund->reason ?: trans('emails.refund_processed.default_reason', [], $locale) }}
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
                    {{ mb_strtoupper(trans('emails.refund_processed.amount_heading', [], $locale)) }}
                </p>

                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td width="50%"></td>
                        <td width="50%">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                {{-- Refunded to the original payment method --}}
                                @if($refund->order?->payment_method)
                                <tr>
                                    <td style="padding: 6px 0; border-bottom: 1px dotted #D8CFB6;">
                                        <span style="font-size: 14px; color: #4E5A74;">{{ trans('emails.refund_processed.payment_method', [], $locale) }}</span>
                                    </td>
                                    <td align="right" style="padding: 6px 0; border-bottom: 1px dotted #D8CFB6;">
                                        <span class="font-mono" style="font-size: 14px; color: #0A1228;">{{ trans('emails.payment_methods.'.$refund->order->payment_method->value, [], $locale) }}</span>
                                    </td>
                                </tr>
                                @endif

                                {{-- Total Refund --}}
                                <tr>
                                    <td style="padding: 12px 0;">
                                        <span class="spec-label" style="color: #0A1228;">{{ mb_strtoupper(trans('emails.refund_processed.total_refund', [], $locale)) }}</span>
                                    </td>
                                    <td align="right" style="padding: 12px 0;">
                                        <span class="font-mono" style="font-size: 18px; color: #0A1228; font-weight: bold;">
                                            {{ number_format((float) $refund->amount_requested, 2) }} €
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
                    {{ mb_strtoupper(trans('emails.refund_processed.processing_heading', [], $locale)) }}
                </p>
                <p style="margin: 0 0 12px 0; font-size: 14px; line-height: 20px; color: #0A1228;">
                    {{ trans('emails.refund_processed.processing_time', ['days' => '5–10'], $locale) }}
                </p>
                <p style="margin: 0; font-size: 14px; line-height: 20px; color: #4E5A74;">
                    {{ trans('emails.refund_processed.notification_note', [], $locale) }}
                </p>
            </td>
        </tr>

        {{-- ═══ CTA BUTTON ═══ --}}
        <tr>
            <td align="center" style="padding: 24px 0;">
                <p style="margin: 0 0 20px 0; font-size: 14px; line-height: 20px; color: #4E5A74;">
                    {{ trans('emails.refund_processed.view_hint', [], $locale) }}
                </p>
                <a href="{{ route('frontend.account.order.detail', ['lang' => $locale, 'order' => $refund->order_id]) }}"
                   class="btn-primary"
                   style="display: inline-block; padding: 13px 26px; background-color: #F59E0B; color: #0A1228 !important; text-decoration: none; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1em; border: 1px solid #F59E0B;">
                    {{ mb_strtoupper(trans('emails.refund_processed.view_button', [], $locale)) }} →
                </a>
            </td>
        </tr>

    </table>
@endsection
