<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $campaign->subject }}</title>
</head>
<body style="margin:0;padding:0;background-color:#F7F3E7;">

{{-- Same mews/purifier clean() already used for blog/page content
     (resources/views/frontend/blog/show.blade.php, page.blade.php) — this
     field is free-form HTML an admin composes for the campaign body, so it
     can't be escaped outright without breaking the email's formatting, but
     rendering it raw let stored script/event-handler markup execute for
     every subscriber the moment they open the email. --}}
{!! clean($campaign->html_content) !!}

{{-- ═══════════════════════════════════════════════════════════════════
     COMPLIANCE FOOTER — server-appended to every campaign send so the
     required unsubscribe mechanism (GDPR Art. 21 / CAN-SPAM) and sender
     postal address (CAN-SPAM) are never dependent on what the admin
     typed into the free-form campaign body above.
     ═══════════════════════════════════════════════════════════════════ --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px;margin:0 auto;">
    <tr>
        <td style="padding:24px 40px;background-color:#0A1228;color:#F7F3E7;font-family:Helvetica,Arial,sans-serif;font-size:12px;line-height:18px;text-align:center;">
            @if(settings('company.address', ''))
                <p style="margin:0 0 8px 0;opacity:0.7;">{{ settings('company.name', 'OeParts') }} &middot; {{ settings('company.address') }}</p>
            @endif
            <p style="margin:0;">
                <a href="{{ $unsubscribeUrl }}" style="color:#F59E0B;text-decoration:underline;">{{ __('emails.newsletter_campaign.unsubscribe', [], $locale ?? 'en') }}</a>
            </p>
        </td>
    </tr>
</table>

</body>
</html>
