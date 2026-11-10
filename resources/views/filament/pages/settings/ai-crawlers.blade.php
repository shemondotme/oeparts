<x-filament-panels::page>
    @php
        $data = $this->getData();
        $max = max(1, collect($data['days'])->max('hits'));
    @endphp

    <style>
        .ac-tiles { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; margin-bottom: 1.25rem; }
        @media (max-width: 640px) { .ac-tiles { grid-template-columns: 1fr; } }
        .ac-tile { border: 1px solid rgba(128,128,128,.3); border-radius: .6rem; padding: .8rem 1rem; }
        .ac-tile b { display: block; font-size: 1.6rem; line-height: 1.1; }
        .ac-bars { display: flex; align-items: flex-end; gap: 2px; height: 90px; border: 1px solid rgba(128,128,128,.3); border-radius: .6rem; padding: .5rem; margin-bottom: 1.25rem; }
        .ac-bar { flex: 1; background: #1d4ed8; border-radius: 2px 2px 0 0; min-height: 2px; opacity: .85; }
        .ac-bar.zero { background: rgba(128,128,128,.35); }
        .ac-table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .ac-table th, .ac-table td { text-align: left; padding: .5rem .6rem; border-bottom: 1px solid rgba(128,128,128,.25); vertical-align: top; }
        .ac-note { font-size: .85rem; opacity: .8; margin-top: 1rem; }
    </style>

    @if (! $data['tracking'])
        <div class="ac-tile" style="border-left: 4px solid #b45309; margin-bottom: 1rem;">Counting is switched off (SEO Control Center → AI crawlers). Nothing new is recorded.</div>
    @endif

    <div class="ac-tiles">
        <div class="ac-tile"><b>{{ number_format($data['total']) }}</b>visits in the last {{ \App\Filament\Pages\Settings\AiCrawlers::DAYS }} days</div>
        <div class="ac-tile"><b>{{ count($data['bots']) }}</b>different AI crawlers seen</div>
        <div class="ac-tile"><b>{{ number_format(collect($data['days'])->last()['hits'] ?? 0) }}</b>visits today</div>
    </div>

    <div class="ac-bars" role="img" aria-label="AI crawler visits per day">
        @foreach ($data['days'] as $day)
            <div class="ac-bar {{ $day['hits'] === 0 ? 'zero' : '' }}" style="height: {{ max(2, (int) round($day['hits'] / $max * 100)) }}%;" title="{{ $day['date'] }}: {{ $day['hits'] }}"></div>
        @endforeach
    </div>

    @if ($data['bots'] === [])
        <div class="ac-tile">No AI crawler has visited yet. Visits show up here once one reads the site.</div>
    @else
        <table class="ac-table" data-ai-crawlers>
            <thead>
                <tr><th>Crawler</th><th>Operator</th><th>What it does</th><th>Visits</th><th>Last seen</th><th>robots.txt</th></tr>
            </thead>
            <tbody>
                @foreach ($data['bots'] as $bot)
                    <tr>
                        <td><strong>{{ $bot['name'] }}</strong></td>
                        <td>{{ $bot['operator'] }}</td>
                        <td>{{ $bot['purpose'] }}</td>
                        <td>{{ number_format($bot['hits']) }}</td>
                        <td>{{ $bot['last_seen'] }}</td>
                        <td>{{ $bot['policy'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="ac-note">
        Crawlers announce themselves by name, and a name can be faked — treat the numbers as a good picture of who is reading the site, not as proof.
        Pages answered from the CDN's own cache never reach this server and are not counted. To change what each crawler may read, edit the AI-crawler rules in the SEO Control Center.
    </p>
</x-filament-panels::page>
