<x-filament-panels::page>
    @php
        $stats = $this->getQueueStats();
        $failedJobs = $this->getRecentFailedJobs();
    @endphp

    {{-- Queue Overview Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="op-card p-5" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 flex items-center justify-center rounded-lg" style="background: rgba(245,158,11,0.1); color: var(--color-warning-500);">
                    <x-heroicon-o-clock class="w-5 h-5" />
                </div>
                <span class="text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-muted);">Pending Jobs</span>
            </div>
            <p class="text-3xl font-bold tabular-nums" style="color: var(--color-text-primary); font-family: var(--font-mono);">
                {{ number_format($stats['total_pending']) }}
            </p>
        </div>

        <div class="op-card p-5" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 flex items-center justify-center rounded-lg" style="background: rgba(59,130,246,0.1); color: var(--color-info-500);">
                    <x-heroicon-o-arrow-path class="w-5 h-5" />
                </div>
                <span class="text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-muted);">Processing</span>
            </div>
            <p class="text-3xl font-bold tabular-nums" style="color: var(--color-text-primary); font-family: var(--font-mono);">
                {{ number_format($stats['processing']) }}
            </p>
        </div>

        <div class="op-card p-5" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 flex items-center justify-center rounded-lg" style="background: rgba(239,68,68,0.1); color: var(--color-danger-500);">
                    <x-heroicon-o-x-circle class="w-5 h-5" />
                </div>
                <span class="text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-muted);">Failed (24h)</span>
            </div>
            <p class="text-3xl font-bold tabular-nums" style="color: var(--color-danger-600); font-family: var(--font-mono);">
                {{ number_format($stats['failed_24h']) }}
            </p>
        </div>

        <div class="op-card p-5" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 flex items-center justify-center rounded-lg" style="background: rgba(16,185,129,0.1); color: var(--color-success-500);">
                    <x-heroicon-o-check-circle class="w-5 h-5" />
                </div>
                <span class="text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-muted);">Completed (1h)</span>
            </div>
            <p class="text-3xl font-bold tabular-nums" style="color: var(--color-success-600); font-family: var(--font-mono);">
                {{ number_format($stats['completed_hour']) }}
            </p>
        </div>
    </div>

    {{-- Per-Queue Breakdown --}}
    <div class="op-card mb-6" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
        <div class="px-6 py-4" style="border-bottom: 1px solid var(--color-border-subtle);">
            <h3 class="text-sm font-bold uppercase tracking-widest" style="color: var(--color-text-muted);">Pending by Queue</h3>
        </div>
        <div class="p-6">
            @if(empty($stats['by_queue']))
                <p class="text-sm" style="color: var(--color-text-muted);">No pending jobs.</p>
            @else
                <div class="space-y-3">
                    @foreach($stats['by_queue'] as $queue => $count)
                        <div class="flex items-center justify-between p-3 rounded-lg" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                            <span class="font-mono text-sm font-bold" style="color: var(--color-text-primary);">{{ $queue }}</span>
                            <span class="font-mono text-sm font-bold tabular-nums px-3 py-1 rounded" style="background: rgba(245,158,11,0.1); color: var(--color-warning-600);">
                                {{ number_format($count) }} pending
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Recent Failed Jobs --}}
    <div class="op-card" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
        <div class="px-6 py-4" style="border-bottom: 1px solid var(--color-border-subtle);">
            <h3 class="text-sm font-bold uppercase tracking-widest" style="color: var(--color-text-muted);">Recent Failed Jobs (Last 20)</h3>
        </div>
        <div class="p-6">
            @if(empty($failedJobs))
                <p class="text-sm" style="color: var(--color-text-muted);">No failed jobs.</p>
            @else
                <div class="space-y-2">
                    @foreach($failedJobs as $job)
                        <div class="flex items-start gap-3 p-3 rounded-lg text-sm" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                            <div class="shrink-0 mt-0.5">
                                <span class="inline-block w-2 h-2 rounded-full" style="background: var(--color-danger-500);"></span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono text-xs font-bold" style="color: var(--color-text-primary);">{{ $job['payload'] }}</span>
                                    <span class="font-mono text-[10px] px-1.5 py-0.5 rounded" style="background: var(--color-bg-surface); color: var(--color-text-muted);">{{ $job['queue'] }}</span>
                                </div>
                                @if($job['exception'])
                                    <p class="mt-1 text-xs truncate" style="color: var(--color-text-muted);">{{ $job['exception'] }}</p>
                                @endif
                            </div>
                            <span class="font-mono text-[10px] shrink-0" style="color: var(--color-text-muted);">
                                {{ \Carbon\Carbon::parse($job['failed_at'])->diffForHumans() }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
