{{-- Brand band + number/date strip shared by every document.
     $docTitle: big title ("INVOICE"); $meta: ordered [label => value] pairs for the strip
     (empty values are dropped); $settings: company settings array. --}}
@php
    [$wordmarkHeavy, $wordmarkLight] = brand_wordmark_parts($settings['company_name']);
    $metaItems = collect($meta)->filter(fn ($value) => filled($value))->all();
    $metaRows = array_chunk($metaItems, 4, true);
@endphp
<table class="band">
    <tr>
        <td>
            <span class="wordmark"><span class="wordmark-heavy">{{ $wordmarkHeavy }}</span><span class="wordmark-light">{{ $wordmarkLight }}</span><span class="wordmark-dot">.</span></span>
        </td>
        <td class="text-right">
            <div class="eyebrow">OeParts · {{ $docTitle }}</div>
            <div class="doc-title">{{ $docTitle }}</div>
        </td>
    </tr>
</table>
@foreach($metaRows as $row)
<table class="strip">
    <tr>
        @foreach($row as $metaLabel => $metaValue)
        <td style="width: {{ 100 / 4 }}%;">
            <div class="label">{{ $metaLabel }}</div>
            <div class="value">{{ $metaValue }}</div>
        </td>
        @endforeach
        @for($pad = count($row); $pad < 4; $pad++)<td style="width: 25%;"></td>@endfor
    </tr>
</table>
@endforeach
