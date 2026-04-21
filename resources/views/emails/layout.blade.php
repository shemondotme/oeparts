<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject ?? 'Email' }}</title>
    <style type="text/css">
        /* Reset styles */
        body, table, td, a {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }
        /* General styles */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 16px;
            line-height: 24px;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            background-color: #f9fafb;
            color: #333333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        .header {
            padding: 32px 40px;
            background-color: #0B3A68;
            color: #ffffff;
            text-align: center;
        }
        .footer {
            padding: 24px 40px;
            background-color: #f9fafb;
            color: #666666;
            font-size: 14px;
            line-height: 20px;
            text-align: center;
        }
        .footer a {
            color: #3b82f6;
            text-decoration: none;
        }
        .footer a:hover {
            text-decoration: underline;
        }
        @media only screen and (max-width: 600px) {
            .container {
                width: 100% !important;
            }
            .header, .footer, td[class="padding"] {
                padding-left: 20px !important;
                padding-right: 20px !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f9fafb;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <!--[if (gte mso 9)|(IE)]>
                <table align="center" border="0" cellspacing="0" cellpadding="0" width="600">
                <tr>
                <td align="center" valign="top" width="600">
                <![endif]-->
                <table class="container" role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);">
                    <!-- Header -->
                    <tr>
                        <td class="header">
                            <h1 style="margin: 0; font-size: 24px; line-height: 32px; font-weight: 600;">
                                {{ config('app.name', 'OEMHub') }}
                            </h1>
                            <p style="margin: 8px 0 0; font-size: 16px; line-height: 24px; opacity: 0.9;">
                                {{ trans('emails.layout.tagline', [], $locale ?? 'en') }}
                            </p>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="background-color: #ffffff;">
                            @yield('content')
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td class="footer">
                            <p style="margin: 0 0 12px;">
                                {{ trans('emails.layout.footer_line1', [], $locale ?? 'en') }}
                            </p>
                            <p style="margin: 0 0 12px;">
                                {{ trans('emails.layout.footer_line2', [], $locale ?? 'en') }}
                            </p>
                            <p style="margin: 0;">
                                <a href="{{ config('app.url') }}">{{ config('app.url') }}</a> |
                                <a href="{{ route('frontend.home', ['lang' => $locale ?? 'en']) }}">{{ trans('emails.layout.home', [], $locale ?? 'en') }}</a> |
                                <a href="{{ route('frontend.account.dashboard', ['lang' => $locale ?? 'en']) }}">{{ trans('emails.layout.account', [], $locale ?? 'en') }}</a>
                            </p>
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