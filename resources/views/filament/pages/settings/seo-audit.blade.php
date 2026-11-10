<x-filament-panels::page>
    @php
        $report = $this->report ?? ['checks' => [], 'summary' => ['critical' => 0, 'warning' => 0, 'info' => 0, 'ok' => 0]];
        $summary = $report['summary'];
        $order = ['critical' => 0, 'warning' => 1, 'info' => 2, 'ok' => 3];
        $labels = ['critical' => 'Critical', 'warning' => 'Warning', 'info' => 'Opportunity', 'ok' => 'Passed'];
        $colors = ['critical' => '#b91c1c', 'warning' => '#b45309', 'info' => '#1d4ed8', 'ok' => '#15803d'];
        $checks = collect($report['checks'])->sortBy(fn ($c) => $order[$c['severity']])->values();
        $groups = $checks->groupBy('group');
    @endphp

    <style>
        .oa-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; margin-bottom: 1.25rem; }
        @media (max-width: 640px) { .oa-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .oa-tile { border: 1px solid rgba(128,128,128,.3); border-radius: .6rem; padding: .8rem 1rem; }
        .oa-tile b { display: block; font-size: 1.6rem; line-height: 1.1; }
        .oa-group { margin-bottom: 1.5rem; }
        .oa-group h3 { font-weight: 700; margin-bottom: .5rem; }
        .oa-check { border: 1px solid rgba(128,128,128,.3); border-left-width: 4px; border-radius: .5rem; padding: .75rem 1rem; margin-bottom: .6rem; }
        .oa-check summary { cursor: pointer; display: flex; gap: .6rem; align-items: baseline; flex-wrap: wrap; }
        .oa-pill { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #fff; border-radius: 999px; padding: .1rem .55rem; }
        .oa-count { font-size: .8rem; opacity: .75; }
        .oa-body { margin-top: .6rem; font-size: .9rem; }
        .oa-body ul { margin: .5rem 0 0 1.1rem; list-style: disc; }
        .oa-body a { text-decoration: underline; }
        .oa-fix { margin-top: .5rem; font-weight: 600; }
    </style>

    <div class="oa-summary">
        @foreach (['critical', 'warning', 'info', 'ok'] as $severity)
            <div class="oa-tile" style="border-left: 4px solid {{ $colors[$severity] }};">
                <b style="color: {{ $colors[$severity] }};">{{ $summary[$severity] }}</b>
                {{ $labels[$severity] }}
            </div>
        @endforeach
    </div>

    @if ($summary['critical'] + $summary['warning'] + $summary['info'] === 0)
        <div class="oa-tile" style="border-left: 4px solid {{ $colors['ok'] }};">Nothing to fix — every check passed.</div>
    @endif

    @foreach ($groups as $groupName => $items)
        <div class="oa-group">
            <h3>{{ $groupName }}</h3>
            @foreach ($items as $check)
                <details class="oa-check" style="border-left-color: {{ $colors[$check['severity']] }};" @if (in_array($check['severity'], ['critical', 'warning'], true)) open @endif data-check="{{ $check['id'] }}">
                    <summary>
                        <span class="oa-pill" style="background: {{ $colors[$check['severity']] }};">{{ $labels[$check['severity']] }}</span>
                        <strong>{{ $check['title'] }}</strong>
                        @if ($check['count'] !== null)
                            <span class="oa-count">{{ number_format($check['count']) }} affected</span>
                        @endif
                    </summary>

                    @if ($check['severity'] !== 'ok')
                        <div class="oa-body">
                            <p>{{ $check['detail'] }}</p>

                            @if (! empty($check['items']))
                                <ul>
                                    @foreach ($check['items'] as $item)
                                        <li>
                                            @if (! empty($item['url']))
                                                <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                                            @else
                                                {{ $item['label'] }}
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                                @if ($check['count'] !== null && $check['count'] > count($check['items']))
                                    <p style="opacity:.7; margin-top:.3rem;">… and {{ number_format($check['count'] - count($check['items'])) }} more.</p>
                                @endif
                            @endif

                            @if ($check['how_to_fix'] !== '')
                                <p class="oa-fix">
                                    How to fix: {{ $check['how_to_fix'] }}
                                    @if (! empty($check['fix_url']))
                                        <a href="{{ $check['fix_url'] }}">{{ $check['fix_label'] ?? 'Open' }} &rarr;</a>
                                    @endif
                                </p>
                            @endif
                        </div>
                    @endif
                </details>
            @endforeach
        </div>
    @endforeach
</x-filament-panels::page>
