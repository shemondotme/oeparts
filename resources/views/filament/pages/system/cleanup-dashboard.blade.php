@use('App\Services\Cleanup\CleanupText', 'T')
<x-filament-panels::page>
    {{-- Self-contained styling: this page's custom layout classes live here (prefixed oc-) so nothing depends on a
         Tailwind rebuild; colours come from the same CSS variables the rest of the admin theme uses, so light and
         dark mode both work. --}}
    <style>
        .oc { display: flex; flex-direction: column; gap: 1.5rem; }
        .oc-card { background: var(--color-bg-surface, #fff); border: 1px solid var(--color-border-subtle, #e5e7eb); border-radius: 1rem; padding: 1.25rem; }
        .oc-card-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; margin-bottom: .75rem; }
        .oc-h { font: 700 .72rem/1.2 ui-monospace, SFMono-Regular, Menlo, monospace; letter-spacing: .12em; text-transform: uppercase; color: var(--color-text-muted, #6b7280); }
        .oc-sub { font-size: .85rem; color: var(--color-text-muted, #6b7280); margin: 0 0 1rem; line-height: 1.5; }
        .oc-muted { color: var(--color-text-muted, #6b7280); font-size: .8rem; line-height: 1.45; }
        .oc-strong { color: var(--color-text-primary, #111827); font-weight: 600; }
        .oc-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
        .oc-grid { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); }
        .oc-tile { border: 1px solid var(--color-border-subtle, #e5e7eb); border-radius: .9rem; padding: .9rem 1rem; }
        .oc-tile-l { font: 700 .66rem/1.2 ui-monospace, monospace; letter-spacing: .1em; text-transform: uppercase; color: var(--color-text-muted, #6b7280); }
        .oc-tile-v { font-size: 1.35rem; font-weight: 700; margin-top: .25rem; color: var(--color-text-primary, #111827); }
        .oc-tile-m { font-size: .75rem; color: var(--color-text-muted, #6b7280); margin-top: .15rem; }
        .oc-bar { height: .55rem; border-radius: 999px; background: rgba(127, 127, 127, .18); overflow: hidden; }
        .oc-bar > i { display: block; height: 100%; border-radius: 999px; transition: width .5s ease-out; background: var(--primary-600, #2563eb); }
        .oc-bar.ok > i { background: var(--success-600, #16a34a); }
        .oc-bar.warn > i { background: var(--warning-500, #f59e0b); }
        .oc-bar.down > i { background: var(--danger-500, #dc2626); }
        .oc-btn { display: inline-flex; align-items: center; gap: .4rem; padding: .4rem .8rem; border-radius: .75rem; font: 700 .7rem/1 ui-sans-serif, system-ui, sans-serif; letter-spacing: .06em; text-transform: uppercase; cursor: pointer; border: 1px solid var(--color-border-subtle, #d1d5db); background: transparent; color: var(--color-text-primary, #111827); text-decoration: none; }
        .oc-btn:hover { background: rgba(127, 127, 127, .1); }
        .oc-btn[disabled], .oc-btn.is-disabled { opacity: .45; cursor: not-allowed; }
        .oc-btn-primary { background: var(--primary-600, #2563eb); border-color: transparent; color: #fff; }
        .oc-btn-primary:hover { background: var(--primary-600, #2563eb); filter: brightness(1.08); }
        .oc-btn-danger { background: var(--danger-500, #dc2626); border-color: transparent; color: #fff; }
        .oc-btn-danger:hover { background: var(--danger-500, #dc2626); filter: brightness(1.08); }
        .oc-btn-warn { border-color: var(--warning-500, #f59e0b); color: var(--warning-500, #b45309); }
        .oc-pill { display: inline-block; padding: .12rem .55rem; border-radius: 999px; font-size: .68rem; font-weight: 700; letter-spacing: .04em; border: 1px solid transparent; }
        .oc-pill.ok { background: rgba(22, 163, 74, .12); color: var(--success-600, #15803d); }
        .oc-pill.warn { background: rgba(245, 158, 11, .14); color: #b45309; }
        .oc-pill.down { background: rgba(220, 38, 38, .12); color: var(--danger-500, #b91c1c); }
        .oc-pill.info { background: rgba(37, 99, 235, .12); color: var(--primary-600, #1d4ed8); }
        .oc-pill.muted { background: rgba(127, 127, 127, .14); color: var(--color-text-muted, #6b7280); }
        .oc-row { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; gap: 1rem; align-items: center; padding: .9rem 0; border-top: 1px solid var(--color-border-subtle, #e5e7eb); }
        .oc-row:first-of-type { border-top: 0; }
        .oc-row-title { font-weight: 700; color: var(--color-text-primary, #111827); display: flex; flex-wrap: wrap; gap: .4rem; align-items: center; }
        .oc-nums { display: flex; flex-wrap: wrap; gap: .25rem 1rem; margin-top: .35rem; font-size: .78rem; color: var(--color-text-muted, #6b7280); }
        .oc-nums b { color: var(--color-text-primary, #111827); font-weight: 600; }
        .oc-ctrl { display: flex; flex-direction: column; gap: .4rem; align-items: flex-start; min-width: 11rem; }
        .oc-actions { display: flex; flex-wrap: wrap; gap: .4rem; justify-content: flex-end; max-width: 15rem; }
        .oc-in { width: 4.5rem; padding: .25rem .4rem; border-radius: .5rem; border: 1px solid var(--color-border-subtle, #d1d5db); background: transparent; color: var(--color-text-primary, #111827); font-size: .85rem; }
        .oc-in-wide { width: 100%; max-width: 14rem; }
        .oc-switch { position: relative; width: 2.2rem; height: 1.2rem; border-radius: 999px; background: rgba(127, 127, 127, .35); border: 0; cursor: pointer; transition: background .2s; flex: none; }
        .oc-switch::after { content: ''; position: absolute; top: .15rem; left: .15rem; width: .9rem; height: .9rem; border-radius: 50%; background: #fff; transition: left .2s; }
        .oc-switch.on { background: var(--success-600, #16a34a); }
        .oc-switch.on::after { left: 1.15rem; }
        .oc-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
        .oc-table th { text-align: left; font: 700 .66rem/1.2 ui-monospace, monospace; letter-spacing: .08em; text-transform: uppercase; color: var(--color-text-muted, #6b7280); padding: .4rem .5rem; border-bottom: 1px solid var(--color-border-subtle, #e5e7eb); }
        .oc-table td { padding: .5rem; border-bottom: 1px solid var(--color-border-subtle, #eef0f3); vertical-align: top; color: var(--color-text-primary, #111827); }
        .oc-table .num { text-align: right; font-variant-numeric: tabular-nums; }
        .oc-note { padding: .75rem .9rem; border-radius: .75rem; font-size: .82rem; line-height: 1.5; border: 1px solid; }
        .oc-note.warn { background: rgba(245, 158, 11, .08); border-color: var(--warning-500, #f59e0b); }
        .oc-note.down { background: rgba(220, 38, 38, .08); border-color: var(--danger-500, #dc2626); }
        .oc-note.ok { background: rgba(22, 163, 74, .08); border-color: var(--success-600, #16a34a); }
        .oc-note.info { background: rgba(37, 99, 235, .06); border-color: rgba(37, 99, 235, .4); }
        .oc-form { display: grid; gap: .9rem 1.25rem; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .oc-field label { display: block; font-size: .78rem; font-weight: 600; margin-bottom: .25rem; color: var(--color-text-primary, #111827); }
        .oc-field .oc-muted { margin-top: .2rem; }
        .oc-err { color: var(--danger-500, #dc2626); font-size: .75rem; margin-top: .2rem; }
        .oc-eq { font-family: ui-monospace, monospace; font-size: .8rem; padding: .5rem .75rem; border-radius: .6rem; background: rgba(127, 127, 127, .1); }
        @media (max-width: 760px) { .oc-row { grid-template-columns: 1fr; } .oc-actions { justify-content: flex-start; max-width: none; } }
    </style>

    @php
        $busy = $this->busyReason;
    @endphp

    <div class="oc">
        @include('filament.pages.system.cleanup._run')
        @include('filament.pages.system.cleanup._overview')

        @if($busy && ! $this->running)
            <div class="oc-note warn">
                <strong>The system is busy right now:</strong> {{ $busy }}
                Previews still work; real cleaning waits until it has finished.
            </div>
        @endif

        @include('filament.pages.system.cleanup._section', [
            'title' => 'Logs & history',
            'subtitle' => 'Visitor and staff logs, SEO and catalog history, monitoring readings, system records. Each keeps its own number of days; automatic cleaning, preview and clean-now work per row.',
            'rows' => $this->targetsIn('logs', 'history', 'system'),
        ])

        @include('filament.pages.system.cleanup._file-logs')

        @include('filament.pages.system.cleanup._backups')

        @include('filament.pages.system.cleanup._section', [
            'title' => 'Other files & folders',
            'subtitle' => 'Temporary uploads, leftover imports, expired cache and session files, dev-only files. Only registered folders are ever looked at, and nothing a running job still needs is touched.',
            'rows' => array_diff_key($this->targetsIn('files'), array_flip(['file_logs', 'update_logs', 'oversized_logs'])),
        ])

        @include('filament.pages.system.cleanup._history')
        @include('filament.pages.system.cleanup._settings')
        @include('filament.pages.system.cleanup._legacy')
    </div>
</x-filament-panels::page>
