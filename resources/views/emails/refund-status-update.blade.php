@extends('emails.layout')

@section('content')
    {{-- ══════════════════════════════════════════════════════════════════════
         REFUND STATUS UPDATE — INDUSTRIAL BLUEPRINT NOTIFICATION
         Focus: Clear status indication, timeline context.
         ══════════════════════════════════════════════════════════════════ --}}

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">

        {{-- ═══ DOC HEADER: Refund Status Update ═══ --}}
        <tr>
            <td style="padding-bottom: 24px; border-bottom: 1px solid #D8CFB6;">
                <p class="spec-label" style="margin: 0 0 8px 0; color: #9A5A00;">
                    FINANCE · REFUND UPDATE
                </p>
                <h2 class="font-display" style="margin: 0; font-size: 24px; line-height: 32px; color: #0A1228;">
                    Refund #{{ $refund->id }}<span class="text-amber">.</span>
                </h2>
                <p style="margin: 12px 0 0 0; font-size: 15px; line-height: 22px; color: #4E5A74;">
                    {{ trans('emails.refund_status_update.greeting', ['name' => $refund->user->name ?? 'Customer'], $locale) }}
                    <br>
                    {{ trans('emails.refund_status_update.body', [], $locale) }}
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
                                        <span class="spec-label" style="color: #4E5A74; display: block; margin-bottom: 8px;">CURRENT STATUS</span>

                                        {{-- Dynamic Status Chip based on status string --}}
                                        @php
                                            $status = strtolower($refund->status?->value ?? 'pending');
                                            $chipBg = '#F1F5F9'; // default gray
                                            $chipText = '#64748B';

                                            if (str_contains($status, 'processing')) {
                                                $chipBg = '#DBEAFE'; $chipText = '#1D4ED8'; // Blue
                                            } elseif (str_contains($status, 'completed') || str_contains($status, 'refunded')) {
                                                $chipBg = '#DCFCE7'; $chipText = '#16A34A'; // Green
                                            } elseif (str_contains($status, 'failed') || str_contains($status, 'rejected')) {
                                                $chipBg = '#FEE2E2'; $chipText = '#DC2626'; // Red
                                            }
                                        @endphp

                                        <span style="display: inline-block; padding: 6px 12px; background-color: {{ $chipBg }}; color: {{ $chipText }}; font-family: 'Courier New', Courier, monospace; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1em; border-radius: 2px;">
                                            {{ strtoupper($refund->status?->value ?? 'Unknown') }}
                                        </span>
                                    </td>
                                </tr>

                                {{-- Timestamp --}}
                                <tr>
                                    <td style="padding-top: 16px;">
                                        <span class="spec-label" style="color: #4E5A74;">UPDATED AT</span>
                                        <span class="font-mono" style="display: block; margin-top: 4px; font-size: 14px; color: #0A1228;">
                                            {{ $refund->updated_at->format('d M Y, H:i') }} CET
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
        @if(filled($refund->admin_note))
        <tr>
            <td style="padding-bottom: 24px;">
                <p class="spec-label" style="margin: 0 0 8px 0; color: #9A5A00;">
                    NOTE FROM SUPPORT
                </p>
                <div style="background-color: #FFFFFF; border-left: 4px solid #F59E0B; padding: 16px; font-size: 14px; line-height: 22px; color: #0A1228;">
                    {!! nl2br(e($refund->admin_note)) !!}
                </div>
            </td>
        </tr>
        @endif

        {{-- ═══ CTA BUTTON ═══ --}}
        <tr>
            <td align="center" style="padding: 24px 0; border-top: 1px solid #D8CFB6;">
                <p style="margin: 0 0 20px 0; font-size: 14px; line-height: 20px; color: #4E5A74;">
                    View full refund details and history.
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
