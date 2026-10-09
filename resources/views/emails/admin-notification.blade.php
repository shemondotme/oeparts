@extends('emails.layout')

@section('system', '1')
@section('eyebrow', $eyebrow)

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td>
                <p class="spec-label" style="margin: 0 0 4px 0;">{{ $label }}</p>
                <h1 style="margin: 0 0 16px 0; font-size: 22px; line-height: 28px; color: #0A1228; font-weight: 800;">{{ $heading }}</h1>

                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #FAF6EA; border: 1px solid #E6DFC6; border-left: 3px solid #F59E0B; margin-bottom: 20px;">
                    <tr>
                        <td style="padding: 14px 16px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                @foreach($rows as $rowLabel => $rowValue)
                                    @if(filled($rowValue))
                                    <tr>
                                        <td style="padding: 4px 12px 4px 0; width: 32%; font-size: 12px; color: #4E5A74; text-transform: uppercase; letter-spacing: 0.08em;">{{ $rowLabel }}</td>
                                        <td style="padding: 4px 0; font-size: 14px; color: #0A1228;">{{ $rowValue }}</td>
                                    </tr>
                                    @endif
                                @endforeach
                            </table>
                        </td>
                    </tr>
                </table>

                @if(filled($body ?? null))
                <p style="margin: 0 0 6px 0; font-size: 12px; color: #4E5A74; text-transform: uppercase; letter-spacing: 0.08em;">{{ $bodyLabel ?? 'Message' }}</p>
                <div style="margin: 0 0 24px 0; padding: 12px 16px; border: 1px solid #E6DFC6; font-size: 14px; line-height: 21px; color: #0A1228;">{!! nl2br(e($body)) !!}</div>
                @endif

                @if(filled($actionUrl ?? null))
                <a href="{{ $actionUrl }}" class="btn-primary" style="display: inline-block; padding: 13px 26px; background-color: #F59E0B; color: #0A1228 !important; text-decoration: none; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1em; border: 1px solid #F59E0B;">{{ $actionLabel ?? 'Open in admin' }}</a>
                @endif
            </td>
        </tr>
    </table>
@endsection
