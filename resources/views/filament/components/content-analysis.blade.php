@php
    /** @var array{score:int,label:string,checks:list<array{id:string,status:string,message:string}>} $analysis */
    $colors = ['good' => '#15803d', 'warn' => '#b45309', 'bad' => '#b91c1c'];
    $scoreColor = $analysis['score'] >= 75 ? $colors['good'] : ($analysis['score'] >= 45 ? $colors['warn'] : $colors['bad']);
    $order = ['bad' => 0, 'warn' => 1, 'good' => 2];
    $checks = collect($analysis['checks'])->sortBy(fn ($c) => $order[$c['status']])->values();
@endphp

<style>
    .oc-ca-head { display: flex; align-items: center; gap: .75rem; margin-bottom: .5rem; }
    .oc-ca-score { font-weight: 700; color: #fff; border-radius: 999px; padding: .15rem .75rem; font-size: .85rem; }
    .oc-ca-list { list-style: none; margin: 0; padding: 0; font-size: .88rem; }
    .oc-ca-list li { display: flex; gap: .55rem; align-items: flex-start; padding: .25rem 0; }
    .oc-ca-dot { flex: none; width: .7rem; height: .7rem; border-radius: 50%; margin-top: .35rem; }
</style>

<div data-content-analysis>
    <div class="oc-ca-head">
        <span class="oc-ca-score" style="background: {{ $scoreColor }};">{{ $analysis['score'] }} / 100</span>
        <strong>{{ $analysis['label'] }}</strong>
    </div>
    <ul class="oc-ca-list">
        @foreach ($checks as $check)
            <li data-check="{{ $check['id'] }}" data-status="{{ $check['status'] }}">
                <span class="oc-ca-dot" style="background: {{ $colors[$check['status']] }};" aria-hidden="true"></span>
                <span>{{ $check['message'] }}</span>
            </li>
        @endforeach
    </ul>
</div>
