{{--
    Dense ops row-list for Cache Dashboard's "Server Health" section — same
    visual language as filament.widgets.system.health-check-rows (colored dot
    + left accent bar, status pill, detail text, inline sparkline, relative
    "last recorded" time). Data comes from CacheHealthRows::getRows().
--}}
<div style="grid-column: 1 / -1;">
    <div class="op-card" style="overflow: hidden;">
        <div class="op-health-row op-health-row-head">
            <span>Metric</span>
            <span>Status</span>
            <span>Detail</span>
            <span>Trend</span>
            <span style="text-align: right;">Last recorded</span>
        </div>

        @foreach ($this->getRows() as $row)
            <div class="op-health-row op-health-{{ $row['state'] }}">
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
            </div>
        @endforeach
    </div>
</div>
