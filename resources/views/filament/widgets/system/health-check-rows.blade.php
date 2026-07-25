{{--
    Dense ops row-list (Option C from the design review) — one row per health
    check: colored dot + accent bar, label/sub-label, status pill, detail +
    latency text, a tiny inline sparkline of the last ~12 snapshots, a
    relative "last checked" time, and a whole-row link into the relevant
    sub-module. Data comes from HealthCheckStats::getRows() — see that class
    for the pill/state/color mapping.
--}}
<div style="grid-column: 1 / -1;">
    <div class="op-card" style="overflow: hidden;">
        <div class="op-health-row op-health-row-head">
            <span>Check</span>
            <span>Status</span>
            <span>Detail</span>
            <span>Trend</span>
            <span style="text-align: right;">Last run</span>
        </div>

        @foreach ($this->getRows() as $row)
            @php($tag = $row['url'] ? 'a' : 'div')
            <{{ $tag }}
                @if ($row['url']) href="{{ $row['url'] }}" @endif
                class="op-health-row op-table-row op-health-{{ $row['state'] }}"
            >
                <span class="op-health-name">
                    <span class="op-dot {{ $row['state'] === 'ok' ? 'op-dot-live' : '' }}" style="{{ $row['state'] !== 'ok' ? 'background: var(--color-text-disabled);' : '' }}"></span>
                    <span>
                        <span style="display: block; font-weight: 600;">{{ $row['label'] }}</span>
                        <span class="op-widget-title" style="text-transform: none; font-weight: 400; opacity: 1; letter-spacing: normal;">{{ $row['sub'] }}</span>
                    </span>
                </span>

                <span class="op-status-pill op-status-pill-{{ $row['state'] }}">{{ $row['pill'] }}</span>

                <span style="color: var(--text-secondary); font-size: 0.8rem;">
                    {{ $row['detail'] }}
                    @if ($row['latency'])
                        <span style="color: var(--text-muted);"> · {{ $row['latency'] }}</span>
                    @endif
                </span>

                <span class="op-spark-bars">
                    @forelse ($row['sparkline'] as $point)
                        <span class="op-spark-bar op-spark-bar-{{ $point }}"></span>
                    @empty
                        <span class="op-widget-title" style="text-transform: none; opacity: 1; letter-spacing: normal;">no history yet</span>
                    @endforelse
                </span>

                <span class="op-widget-title" style="text-align: right; text-transform: none; opacity: 1; letter-spacing: normal;">{{ $row['lastChecked'] }}</span>
            </{{ $tag }}>
        @endforeach
    </div>
</div>
