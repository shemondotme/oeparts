@extends('emails.layout')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding: 40px 40px 20px; text-align: center;">
                <h1 style="margin: 0; font-size: 24px; line-height: 32px; color: #333333; font-weight: 600;">
                    {{ trans('emails.contact_reply.title', [], $locale) }}
                </h1>
            </td>
        </tr>
        <tr>
            <td style="padding: 20px 40px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td style="padding: 24px; background-color: #f9fafb; border-radius: 8px; margin-bottom: 20px;">
                            <p style="margin: 0 0 8px; font-size: 12px; color: #999999; text-transform: uppercase; letter-spacing: 0.05em;">
                                {{ trans('emails.contact_reply.original_message', [], $locale) }}
                            </p>
                            <p style="margin: 0; font-size: 14px; line-height: 20px; color: #666666; font-style: italic;">
                                {{ $contactMessage->message }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 0 40px 20px;">
                <p style="margin: 0; font-size: 16px; line-height: 24px; color: #333333;">
                    {!! nl2br(e($replyBody)) !!}
                </p>
            </td>
        </tr>
    </table>
@endsection
