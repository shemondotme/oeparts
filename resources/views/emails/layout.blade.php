<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject ?? 'OeParts Notification' }}</title>
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
            background-color: #F7F3E7; /* Ivory */
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; /* Fallback for Inter */
            color: #0A1228; /* Ink */
            -webkit-font-smoothing: antialiased;
        }

        /* Blueprint Grid Texture Simulation (CSS only) */
        .bg-grid-pattern {
            background-color: #F7F3E7;
            background-image:
                linear-gradient(to right, rgba(10,18,40,0.05) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(10,18,40,0.05) 1px, transparent 1px);
            background-size: 24px 24px;
        }

        /* Container */
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #FFFFFF; /* Paper */
            border: 1px solid #D8CFB6; /* Rule */
        }

        /* Header - Dark Ink */
        .header {
            background-color: #0A1228; /* Ink */
            color: #F7F3E7; /* Ivory text */
            padding: 32px 40px 24px 40px;
            border-bottom: 4px solid #F59E0B; /* Amber accent strip */
        }

        /* Footer - Dark Ink */
        .footer {
            background-color: #0A1228; /* Ink */
            color: #F7F3E7; /* Ivory text */
            padding: 32px 40px;
            border-top: 1px solid #1D2A44; /* Rule dark */
            font-size: 12px;
            line-height: 18px;
        }

        /* Typography Helpers */
        .font-display { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-weight: 800; letter-spacing: -0.03em; }
        .font-mono { font-family: 'Courier New', Courier, monospace; }
        .text-amber { color: #F59E0B; }
        .text-ink-muted { color: #4E5A74; }
        .text-rule { color: #D8CFB6; }

        /* Buttons */
        .btn-primary {
            display: inline-block;
            padding: 14px 28px;
            background-color: #0A1228; /* Ink */
            color: #F7F3E7 !important; /* Ivory */
            text-decoration: none;
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            border: 1px solid #0A1228;
        }
        .btn-primary:hover {
            background-color: #F59E0B; /* Amber */
            color: #0A1228 !important; /* Ink */
            border-color: #F59E0B;
        }

        /* Spec Labels */
        .spec-label {
            font-family: 'Courier New', Courier, monospace;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.22em;
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
<body class="bg-grid-pattern">

    <!-- Preheader (Hidden) -->
    <div style="display:none;font-size:1px;color:#F7F3E7;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">
        {{ $preheader ?? 'OeParts Notification' }}
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

                    <!-- ═══ HEADER: Doc Header Style ═══ -->
                    <tr>
                        <td class="header">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td>
                                        <!-- Eyebrow / Spec Label -->
                                        <p class="spec-label" style="margin: 0 0 12px 0;">
                                            § OEPARTS · NOTIFICATION
                                        </p>

                                        <!-- Logo / Brand Name -->
                                        <h1 class="font-display" style="margin: 0; font-size: 28px; line-height: 32px; color: #F7F3E7;">
                                            Oe<span class="text-amber">·</span>Parts
                                        </h1>

                                        <!-- Tagline -->
                                        <p style="margin: 8px 0 0 0; font-size: 14px; line-height: 20px; color: #F7F3E7; opacity: 0.8;">
                                            {{ trans('emails.layout.tagline', [], $locale ?? 'en') ?: 'Genuine OEM Auto Parts Index' }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- ═══ BODY CONTENT ═══ -->
                    <tr>
                        <td class="content-padding" style="background-color: #FFFFFF; padding: 40px;">
                            @yield('content')
                        </td>
                    </tr>

                    <!-- ═══ FOOTER: Colophon Style ═══ -->
                    <tr>
                        <td class="footer">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td style="padding-bottom: 20px; border-bottom: 1px solid #1D2A44;">
                                        <p class="spec-label" style="margin: 0 0 8px 0; color: #F59E0B;">
                                            § COLOPHON
                                        </p>
                                        <p style="margin: 0; font-size: 14px; line-height: 20px; color: #F7F3E7;">
                                            <strong>OeParts Europe</strong><br>
                                            Genuine Parts Distribution Network
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-top: 20px;">
                                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td style="font-size: 12px; line-height: 18px; color: #F7F3E7; opacity: 0.6;">
                                                    <p style="margin: 0 0 8px 0;">
                                                        {{ trans('emails.layout.footer_line1', [], $locale ?? 'en') ?: 'You are receiving this email because you have an account or placed an order with OeParts.' }}
                                                    </p>
                                                    <p style="margin: 0;">
                                                        <a href="{{ config('app.url') }}" style="color: #F59E0B; text-decoration: underline;">{{ config('app.url') }}</a>
                                                        <span style="color: #4E5A74;"> | </span>
                                                        <a href="{{ route('frontend.home', ['lang' => $locale ?? 'en']) }}" style="color: #F59E0B; text-decoration: underline;">Home</a>
                                                        <span style="color: #4E5A74;"> | </span>
                                                        <a href="{{ route('frontend.account.dashboard', ['lang' => $locale ?? 'en']) }}" style="color: #F59E0B; text-decoration: underline;">Account</a>
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
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
