{{-- Seller / buyer blocks side by side. $parties: list of ['label' => string, 'lines' => string[]];
     the first line of each block is printed bold, blank lines are dropped. --}}
@php $count = count($parties); @endphp
<table class="section">
    <tr>
        @foreach($parties as $index => $party)
        @if($index > 0)<td class="party-spacer"></td>@endif
        <td style="width: {{ $count === 3 ? 31 : 47 }}%;">
            <div class="party">
                <div class="label">{{ $party['label'] }}</div>
                @foreach(array_values(array_filter($party['lines'], fn ($line) => filled($line))) as $lineIndex => $line)
                    <div>@if($lineIndex === 0)<strong>{{ $line }}</strong>@else{{ $line }}@endif</div>
                @endforeach
            </div>
        </td>
        @endforeach
    </tr>
</table>
