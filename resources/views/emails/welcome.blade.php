@extends('emails.layout')

@section('content')
    {{-- ══════════════════════════════════════════════════════════════════════
         WELCOME EMAIL — INDUSTRIAL BLUEPRINT ONBOARDING
         Technical, authoritative, guiding.
         ══════════════════════════════════════════════════════════════════ --}}

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">

        {{-- ═══ DOC HEADER: Welcome ═══ --}}
        <tr>
            <td style="padding-bottom: 24px; border-bottom: 1px solid #D8CFB6;">
                <p class="spec-label" style="margin: 0 0 8px 0; color: #9A5A00;">
                    § ACCOUNT · ACTIVATED
                </p>
                <h2 class="font-display" style="margin: 0; font-size: 24px; line-height: 32px; color: #0A1228;">
                    Welcome to OeParts<span class="text-amber">.</span>
                </h2>
                <p style="margin: 12px 0 0 0; font-size: 15px; line-height: 22px; color: #4E5A74;">
                    {{ trans('emails.welcome.greeting', ['name' => $user->name], $locale) }}
                </p>
            </td>
        </tr>

        {{-- ═══ INTRO PROSE ═══ --}}
        <tr>
            <td style="padding: 24px 0;">
                <p style="margin: 0 0 16px 0; font-size: 15px; line-height: 24px; color: #0A1228;">
                    {{ trans('emails.welcome.body_intro', [], $locale) ?: 'You now have access to Europe’s most comprehensive index of genuine OEM auto parts. Search by part number, compare verified suppliers, and track your orders in real-time.' }}
                </p>
                <p style="margin: 0; font-size: 15px; line-height: 24px; color: #0A1228;">
                    {{ trans('emails.welcome.body_secondary', [], $locale) ?: 'Our platform is built for precision. Every part is cross-referenced, every supplier is vetted, and every transaction is secure.' }}
                </p>
            </td>
        </tr>

        {{-- ═══ ONBOARDING STEPS (Spec Ledger) ═══ --}}
        <tr>
            <td style="padding-bottom: 24px;">
                <p class="spec-label" style="margin: 0 0 12px 0; color: #9A5A00;">
                    § GETTING STARTED
                </p>

                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border: 1px solid #D8CFB6; background-color: #F7F3E7;">
                    {{-- Step 1 --}}
                    <tr>
                        <td style="padding: 16px; border-bottom: 1px solid #D8CFB6;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td width="40" valign="top">
                                        <span class="font-mono" style="font-size: 14px; font-weight: bold; color: #0A1228;">01</span>
                                    </td>
                                    <td valign="top">
                                        <strong style="display: block; margin-bottom: 4px; color: #0A1228; font-size: 15px;">Search by OEM Number</strong>
                                        <p style="margin: 0; font-size: 14px; line-height: 20px; color: #4E5A74;">
                                            Enter any manufacturer part number to find exact matches and cross-references across 27 EU countries.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    {{-- Step 2 --}}
                    <tr>
                        <td style="padding: 16px; border-bottom: 1px solid #D8CFB6;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td width="40" valign="top">
                                        <span class="font-mono" style="font-size: 14px; font-weight: bold; color: #0A1228;">02</span>
                                    </td>
                                    <td valign="top">
                                        <strong style="display: block; margin-bottom: 4px; color: #0A1228; font-size: 15px;">Verify Supplier & Price</strong>
                                        <p style="margin: 0; font-size: 14px; line-height: 20px; color: #4E5A74;">
                                            Compare prices from verified distributors. All parts are guaranteed genuine with full warranty support.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    {{-- Step 3 --}}
                    <tr>
                        <td style="padding: 16px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td width="40" valign="top">
                                        <span class="font-mono" style="font-size: 14px; font-weight: bold; color: #0A1228;">03</span>
                                    </td>
                                    <td valign="top">
                                        <strong style="display: block; margin-bottom: 4px; color: #0A1228; font-size: 15px;">Track Your Order</strong>
                                        <p style="margin: 0; font-size: 14px; line-height: 20px; color: #4E5A74;">
                                            Monitor shipment status from dispatch to delivery via your account dashboard.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ═══ CTA BUTTON ═══ --}}
        <tr>
            <td align="center" style="padding: 24px 0; border-top: 1px solid #D8CFB6; border-bottom: 1px solid #D8CFB6;">
                <a href="{{ route('frontend.search.console', ['lang' => $locale]) }}"
                   class="btn-primary"
                   style="display: inline-block; padding: 14px 28px; background-color: #0A1228; color: #F7F3E7 !important; text-decoration: none; font-family: 'Courier New', Courier, monospace; font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.18em; border: 1px solid #0A1228;">
                    START SEARCHING →
                </a>
            </td>
        </tr>

        {{-- ═══ SUPPORT INFO ═══ --}}
        <tr>
            <td style="padding-top: 24px;">
                <p style="margin: 0 0 8px 0; font-size: 14px; line-height: 20px; color: #4E5A74;">
                    {{ trans('emails.welcome.support_text', [], $locale) ?: 'Need assistance? Our technical support team is available MON–FRI · 09:00–18:00 CET.' }}
                </p>
                <p style="margin: 0; font-size: 14px; line-height: 20px;">
                    <a href="mailto:{{ settings('contact.email', 'info@oeparts.lt') }}" style="color: #9A5A00; text-decoration: underline; font-family: 'Courier New', Courier, monospace;">
                        {{ settings('contact.email', 'info@oeparts.lt') }}
                    </a>
                </p>
            </td>
        </tr>

    </table>
@endsection
