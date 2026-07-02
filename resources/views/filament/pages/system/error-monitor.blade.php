<x-filament-panels::page>
    @php
        $stats = $this->getErrorStats();
        $exceptions = $this->getExceptionLog();
        $failedJobs = $this->getFailedJobStats();
    @endphp

    {{-- Error Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="op-card p-5" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 flex items-center justify-center rounded-lg" style="background: rgba(239,68,68,0.1); color: var(--danger-500);">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5" />
                </div>
                <span class="text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-muted);">Exceptions (24h)</span>
            </div>
            <p class="text-3xl font-bold tabular-nums" style="color: var(--color-text-primary); font-family: var(--font-mono);">
                {{ number_format($stats['total_exceptions_24h']) }}
            </p>
        </div>

        <div class="op-card p-5" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 flex items-center justify-center rounded-lg" style="background: rgba(245,158,11,0.1); color: var(--warning-500);">
                    <x-heroicon-o-x-circle class="w-5 h-5" />
                </div>
                <span class="text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-muted);">Failed Jobs (24h)</span>
            </div>
            <p class="text-3xl font-bold tabular-nums" style="color: var(--color-text-primary); font-family: var(--font-mono);">
                {{ number_format($stats['total_failed_jobs_24h']) }}
            </p>
        </div>

        <div class="op-card p-5" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 flex items-center justify-center rounded-lg" style="background: rgba(59,130,246,0.1); color: var(--info-500);">
                    <x-heroicon-o-tag class="w-5 h-5" />
                </div>
                <span class="text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-muted);">Unique Types</span>
            </div>
            <p class="text-3xl font-bold tabular-nums" style="color: var(--color-text-primary); font-family: var(--font-mono);">
                {{ number_format($stats['unique_exceptions']) }}
            </p>
        </div>

        <div class="op-card p-5" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 flex items-center justify-center rounded-lg" style="background: rgba(16,185,129,0.1); color: var(--success-500);">
                    <x-heroicon-o-clock class="w-5 h-5" />
                </div>
                <span class="text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-muted);">Log File</span>
            </div>
            <p class="text-lg font-bold" style="color: var(--color-text-primary); font-family: var(--font-mono);">
                laravel.log
            </p>
            <p class="text-xs mt-1" style="color: var(--color-text-muted);">
                {{ number_format(filesize(storage_path('logs/laravel.log')) / 1024) }}KB
            </p>
        </div>
    </div>

    {{-- Exception Breakdown --}}
    @if(!empty($stats['by_exception']))
    <div class="op-card mb-6" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
        <div class="px-6 py-4" style="border-bottom: 1px solid var(--color-border-subtle);">
            <h3 class="text-sm font-bold uppercase tracking-widest" style="color: var(--color-text-muted);">Exceptions by Type (24h)</h3>
        </div>
        <div class="p-6">
            <div class="space-y-3">
                @foreach($stats['by_exception'] as $type => $count)
                    <div class="flex items-center justify-between p-3 rounded-lg" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                        <span class="font-mono text-xs font-bold" style="color: var(--color-text-primary);">{{ class_basename($type) }}</span>
                        <span class="font-mono text-sm font-bold tabular-nums px-3 py-1 rounded" style="background: rgba(239,68,68,0.1); color: var(--danger-600);">
                            {{ $count }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Recent Exceptions --}}
    <div class="op-card" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
        <div class="px-6 py-4" style="border-bottom: 1px solid var(--color-border-subtle);">
            <h3 class="text-sm font-bold uppercase tracking-widest" style="color: var(--color-text-muted);">Recent Exceptions (Last 50)</h3>
        </div>
        <div class="p-6">
            @if(empty($exceptions))
                <p class="text-sm" style="color: var(--color-text-muted);">No exceptions found in log.</p>
            @else
                <div class="space-y-2">
                    @foreach($exceptions as $error)
                        <div class="flex items-start gap-3 p-3 rounded-lg text-sm" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                            <div class="shrink-0 mt-0.5">
                                <span class="inline-block w-2 h-2 rounded-full" style="background: var(--danger-500);"></span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-xs font-bold" style="color: var(--danger-600);">{{ ucfirst($error['type']) }}</span>
                                    <span class="font-mono text-[10px] px-1.5 py-0.5 rounded" style="background: var(--color-bg-surface); color: var(--color-text-muted);">{{ $error['file'] }}:{{ $error['line'] }}</span>
                                </div>
                                <p class="mt-1 text-xs truncate" style="color: var(--color-text-muted);">{{ $error['message'] }}</p>
                            </div>
                            <span class="font-mono text-[10px] shrink-0" style="color: var(--color-text-muted);">
                                {{ \Carbon\Carbon::parse($error['time'])->diffForHumans() }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
