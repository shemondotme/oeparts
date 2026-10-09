{{ $heading }}

@foreach($rows as $rowLabel => $rowValue)
@if(filled($rowValue))
{{ $rowLabel }}: {{ $rowValue }}
@endif
@endforeach
@if(filled($body ?? null))

{{ $bodyLabel ?? 'Message' }}:
{{ $body }}
@endif
@if(filled($actionUrl ?? null))

{{ $actionLabel ?? 'Open in admin' }}: {{ $actionUrl }}
@endif
