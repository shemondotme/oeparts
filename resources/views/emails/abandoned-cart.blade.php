@extends('emails.layout')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding: 40px 40px 20px; text-align: center;">
                <h1 style="margin: 0; font-size: 24px; line-height: 32px; color: #333333; font-weight: 600;">
                    {{ trans('emails.abandoned_cart.title', [], $locale) }}
                </h1>
                <p style="margin: 20px 0 0; font-size: 16px; line-height: 24px; color: #666666;">
                    {{ trans('emails.abandoned_cart.body', [], $locale) }}
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding: 20px 40px; text-align: center;">
                <a href="{{ route('frontend.cart.index', ['lang' => $locale]) }}" style="display: inline-block; padding: 12px 24px; background-color: #F59E0B; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 16px; line-height: 24px; font-weight: 500;">
                    {{ trans('emails.abandoned_cart.cta', [], $locale) }}
                </a>
            </td>
        </tr>
    </table>
@endsection
