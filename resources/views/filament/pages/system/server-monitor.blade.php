<x-filament-panels::page>
    @php
        $cpu = $this->getCpuLoad();
        $memory = $this->getMemoryStats();
        $disk = $this->getDiskStats();
        $php = $this->getPhpInfo();
        $laravel = $this->getLaravelInfo();
    @endphp

    {{-- Resource Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {{-- CPU Load --}}
        <div class="op-card p-5" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 flex items-center justify-center rounded-lg" style="background: rgba(59,130,246,0.1); color: var(--color-info-500);">
                    <x-heroicon-o-cpu-chip class="w-5 h-5" />
                </div>
                <span class="text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-muted);">CPU Load</span>
            </div>
            <p class="text-3xl font-bold tabular-nums" style="color: var(--color-text-primary); font-family: var(--font-mono);">
                {{ $cpu['1min'] }}
            </p>
            <p class="text-xs mt-1" style="color: var(--color-text-muted);">
                5m: {{ $cpu['5min'] }} · 15m: {{ $cpu['15min'] }}
            </p>
        </div>

        {{-- Memory --}}
        <div class="op-card p-5" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 flex items-center justify-center rounded-lg" style="background: rgba(168,85,247,0.1); color: var(--color-primary-500);">
                    <x-heroicon-o-square-3-stack-3d class="w-5 h-5" />
                </div>
                <span class="text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-muted);">Memory</span>
            </div>
            <p class="text-3xl font-bold tabular-nums" style="color: var(--color-text-primary); font-family: var(--font-mono);">
                {{ $memory['usage_mb'] }}<span class="text-lg">MB</span>
            </p>
            <div class="mt-2 w-full h-1.5 rounded-full" style="background: var(--color-bg-inset);">
                <div class="h-full rounded-full transition-all" style="width: {{ $memory['usage_percent'] }}%; background: {{ $memory['usage_percent'] > 80 ? 'var(--color-danger-500)' : 'var(--color-info-500)' }};"></div>
            </div>
            <p class="text-xs mt-1" style="color: var(--color-text-muted);">
                {{ $memory['usage_percent'] }}% of {{ $memory['limit_mb'] }}MB
            </p>
        </div>

        {{-- Disk --}}
        <div class="op-card p-5" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 flex items-center justify-center rounded-lg" style="background: rgba(245,158,11,0.1); color: var(--color-warning-500);">
                    <x-heroicon-o-server-stack class="w-5 h-5" />
                </div>
                <span class="text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-muted);">Disk</span>
            </div>
            <p class="text-3xl font-bold tabular-nums" style="color: var(--color-text-primary); font-family: var(--font-mono);">
                {{ $disk['used_gb'] }}<span class="text-lg">GB</span>
            </p>
            <div class="mt-2 w-full h-1.5 rounded-full" style="background: var(--color-bg-inset);">
                <div class="h-full rounded-full transition-all" style="width: {{ $disk['usage_percent'] }}%; background: {{ $disk['usage_percent'] > 90 ? 'var(--color-danger-500)' : 'var(--color-warning-500)' }};"></div>
            </div>
            <p class="text-xs mt-1" style="color: var(--color-text-muted);">
                {{ $disk['usage_percent'] }}% of {{ $disk['total_gb'] }}GB
            </p>
        </div>

        {{-- PHP Version --}}
        <div class="op-card p-5" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 flex items-center justify-center rounded-lg" style="background: rgba(16,185,129,0.1); color: var(--color-success-500);">
                    <x-heroicon-o-information-circle class="w-5 h-5" />
                </div>
                <span class="text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-muted);">Runtime</span>
            </div>
            <p class="text-3xl font-bold" style="color: var(--color-text-primary); font-family: var(--font-mono);">
                PHP {{ $php['version'] }}
            </p>
            <p class="text-xs mt-1" style="color: var(--color-text-muted);">
                {{ ucfirst($php['sapi']) }}
            </p>
        </div>
    </div>

    {{-- PHP Configuration --}}
    <div class="op-card mb-6" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
        <div class="px-6 py-4" style="border-bottom: 1px solid var(--color-border-subtle);">
            <h3 class="text-sm font-bold uppercase tracking-widest" style="color: var(--color-text-muted);">PHP Configuration</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach([
                    'Memory Limit' => $php['memory_limit'],
                    'Max Execution Time' => $php['max_execution_time'] . 's',
                    'Upload Max Filesize' => $php['upload_max_filesize'],
                    'Post Max Size' => $php['post_max_size'],
                ] as $label => $value)
                    <div class="flex items-center justify-between p-3 rounded-lg" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                        <span class="text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-muted);">{{ $label }}</span>
                        <span class="font-mono text-sm font-bold" style="color: var(--color-text-primary);">{{ $value }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Laravel Info --}}
    <div class="op-card mb-6" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
        <div class="px-6 py-4" style="border-bottom: 1px solid var(--color-border-subtle);">
            <h3 class="text-sm font-bold uppercase tracking-widest" style="color: var(--color-text-muted);">Laravel Environment</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach([
                    'Version' => $laravel['version'],
                    'Environment' => $laravel['environment'],
                    'Debug Mode' => $laravel['debug'],
                    'Cache Driver' => $laravel['cache_driver'],
                    'Queue Driver' => $laravel['queue_driver'],
                    'Session Driver' => $laravel['session_driver'],
                    'Database' => $laravel['database'],
                    'Timezone' => $laravel['timezone'],
                ] as $label => $value)
                    <div class="flex items-center justify-between p-3 rounded-lg" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                        <span class="text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-muted);">{{ $label }}</span>
                        <span class="font-mono text-sm font-bold" style="color: var(--color-text-primary);">{{ $value }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- PHP Extensions --}}
    <div class="op-card" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
        <div class="px-6 py-4" style="border-bottom: 1px solid var(--color-border-subtle);">
            <h3 class="text-sm font-bold uppercase tracking-widest" style="color: var(--color-text-muted);">Loaded Extensions ({{ count($php['extensions']) }})</h3>
        </div>
        <div class="p-6">
            <div class="flex flex-wrap gap-2">
                @foreach($php['extensions'] as $ext)
                    <span class="font-mono text-[10px] font-bold px-2 py-1 rounded" style="background: var(--color-bg-inset); color: var(--color-text-muted); border: 1px solid var(--color-border-subtle);">
                        {{ $ext }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-panels::page>
