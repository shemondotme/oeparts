<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject ?? trans('emails.layout.default_subject', [], $locale ?? null) }}</title>
    <style type="text/css">
        /* =========================================
           INDUSTRIAL BLUEPRINT EMAIL RESET
           ========================================= */

        /* Client-specific resets */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }

        /* General typography & layout */
        body {
            margin: 0 !important;
            padding: 0 !important;
            background-color: #F2EFE4; /* Ivory */
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; /* Fallback for Inter */
            color: #0A1228; /* Ink */
            -webkit-font-smoothing: antialiased;
        }

        /* Container */
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #FFFFFF; /* Paper */
            border: 1px solid #E6DFC6; /* Rule */
        }

        /* Header - slim Ink band with Amber rule (same treatment as the PDF invoices) */
        .header {
            background-color: #0A1228; /* Ink */
            color: #F7F3E7; /* Ivory text */
            padding: 20px 32px 18px 32px;
            border-bottom: 4px solid #F59E0B; /* Amber accent strip */
        }

        /* Footer - slim Ink */
        .footer {
            background-color: #0A1228; /* Ink */
            color: #F7F3E7; /* Ivory text */
            padding: 20px 32px;
            font-size: 12px;
            line-height: 18px;
        }

        /* Typography Helpers */
        .font-display { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-weight: 800; letter-spacing: -0.03em; }
        .font-mono { font-family: 'Courier New', Courier, monospace; }
        .text-amber { color: #F59E0B; }
        .text-ink-muted { color: #4E5A74; }
        .text-rule { color: #D8CFB6; }

        /* Buttons: amber with ink text */
        .btn-primary {
            display: inline-block;
            padding: 13px 26px;
            background-color: #F59E0B; /* Amber */
            color: #0A1228 !important; /* Ink */
            text-decoration: none;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            border: 1px solid #F59E0B;
        }
        .btn-primary:hover {
            background-color: #0A1228; /* Ink */
            color: #F7F3E7 !important; /* Ivory */
            border-color: #0A1228;
        }

        /* Status label above the headline */
        .spec-label {
            font-family: 'Courier New', Courier, monospace;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: #9A5A00; /* Amber Ink */
            font-weight: bold;
        }

        /* Mobile Responsiveness */
        @media only screen and (max-width: 600px) {
            .container { width: 100% !important; border-left: none; border-right: none; }
            .header, .footer, .content-padding { padding-left: 20px !important; padding-right: 20px !important; }
            .mobile-stack { display: block !important; width: 100% !important; }
        }
    </style>
</head>
<body>

    <!-- Preheader (Hidden) -->
    <div style="display:none;font-size:1px;color:#F7F3E7;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">
        {{ $preheader ?? trans('emails.layout.default_subject', [], $locale ?? null) }}
    </div>

    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td align="center" style="padding: 40px 0;">

                <!--[if (gte mso 9)|(IE)]>
                <table align="center" border="0" cellspacing="0" cellpadding="0" width="600">
                <tr>
                <td align="center" valign="top" width="600">
                <![endif]-->

                <table class="container" role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px;">

                    <!-- ═══ HEADER: slim brand band ═══ -->
                    <tr>
                        <td class="header">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td valign="middle">
                                        @php [$wordmarkHeavy, $wordmarkLight] = brand_wordmark_parts(settings('general.site_name', 'OeParts')); @endphp
                                        <span class="font-display" style="font-size: 24px; line-height: 28px; color: #F7F3E7;"><span style="font-weight: 800;">{{ $wordmarkHeavy }}</span><span style="font-weight: 400; opacity: 0.75;">{{ $wordmarkLight }}</span><span class="text-amber" style="color: #F59E0B;">.</span></span>
                                    </td>
                                    <td valign="middle" align="right">
                                        <span class="spec-label" style="color: #F59E0B; font-size: 10px; letter-spacing: 0.2em;">@hasSection('eyebrow')@yield('eyebrow')@else{{ trans('emails.layout.header_text', [], $locale ?? null) }}@endif</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- ═══ BODY CONTENT ═══ -->
                    <tr>
                        <td class="content-padding" style="background-color: #FFFFFF; padding: 32px;">
                            @yield('content')
                        </td>
                    </tr>

                    <!-- ═══ FOOTER: slim ═══ -->
                    <tr>
                        <td class="footer">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td style="font-size: 13px; line-height: 19px; color: #F7F3E7;">
                                        <strong>{{ settings('company.name', 'OeParts') }}</strong> · {{ ui_copy('footer_site_tagline', 'footer.site_tagline') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-top: 8px; font-size: 11.5px; line-height: 17px; color: #AAB2C5;">
                                        @hasSection('system')
                                            {{ trans('emails.layout.system_footer', ['host' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: config('app.url')], $locale ?? 'en') }}
                                        @else
                                            {{ trans('emails.layout.footer_line2', [], $locale ?? 'en') }}<br>
                                            {{ trans('emails.layout.footer_line1', ['year' => now()->year], $locale ?? 'en') }}
                                        @endif
                                    </td>
                                </tr>
                                @if(! $__env->hasSection('system'))
                                <tr>
                                    <td style="padding-top: 8px; font-size: 11.5px; line-height: 17px;">
                                        <a href="{{ config('app.url') }}" style="color: #F59E0B; text-decoration: underline;">{{ config('app.url') }}</a>
                                        <span style="color: #4E5A74;"> | </span>
                                        <a href="{{ route('frontend.home', ['lang' => $locale ?? 'en']) }}" style="color: #F59E0B; text-decoration: underline;">Home</a>
                                        <span style="color: #4E5A74;"> | </span>
                                        <a href="{{ route('frontend.account.dashboard', ['lang' => $locale ?? 'en']) }}" style="color: #F59E0B; text-decoration: underline;">Account</a>
                                    </td>
                                </tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                </table>

                <!--[if (gte mso 9)|(IE)]>
                </td>
                </tr>
                </table>
                <![endif]-->

            </td>
        </tr>
    </table>
</body>
</html>
