<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header --}}
        <div class="op-card relative overflow-hidden p-6 page-header-gradient page-header-border">
            <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold tracking-tight flex items-center gap-2" style="color: var(--color-text-on-accent, #ffffff); font-family: var(--font-display);">
                        <x-heroicon-o-clock class="w-5 h-5" style="color: var(--color-warning-500);" />
                        Scheduled Tasks
                    </h2>
                    <p class="mt-1 text-sm max-w-2xl leading-relaxed" style="color: var(--color-text-muted);">
                        View all registered scheduled commands and their recent execution history. Run tasks manually for testing.
                    </p>
                </div>
                <div class="flex items-center gap-2 text-xs font-mono px-3 py-1.5 rounded-lg shrink-0 w-fit"
                    style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: var(--color-success-400);">
                    <span class="h-2 w-2 rounded-full animate-pulse" style="background: var(--color-success-500);"></span>
                    POLLING 60s
                </div>
            </div>
        </div>

        {{-- Scheduled Commands --}}
        @php $tasks = $this->getScheduledTasks(); @endphp

        <div class="op-card overflow-hidden" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="px-6 py-4 flex items-center gap-3" style="border-bottom: 1px solid var(--color-border-subtle); background: var(--color-bg-inset);">
                <div class="p-1.5 rounded-lg" style="background: var(--color-bg-surface); color: var(--color-text-muted);">
                    <x-heroicon-o-list-bullet class="w-4 h-4" />
                </div>
                <h3 class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">
                    Registered Commands
                </h3>
            </div>

            <div class="p-6">
                @if(empty($tasks))
                    <div class="text-center py-12">
                        <x-heroicon-o-clock class="w-12 h-12 mx-auto mb-4" style="color: var(--color-text-muted);" />
                        <p class="text-sm font-medium" style="color: var(--color-text-muted);">No scheduled tasks found.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--color-border-subtle);">
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Command</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Description</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Schedule</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tasks as $task)
                                    <tr style="border-bottom: 1px solid var(--color-border-subtle);" class="op-table-row">
                                        <td class="px-4 py-3 font-mono text-xs" style="color: var(--color-text-primary);">
                                            {{ $task['command'] }}
                                        </td>
                                        <td class="px-4 py-3 text-xs" style="color: var(--color-text-secondary);">
                                            {{ $task['description'] }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium font-mono"
                                                style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle); color: var(--color-text-secondary);">
                                                {{ $task['schedule'] }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <button wire:click="runTask('{{ $task['command'] }}')"
                                                x-data
                                                x-on:click="if (!confirm('Run this task now?')) $event.preventDefault()"
                                                class="op-focus-ring op-press inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold transition-all duration-200"
                                                style="background: var(--color-info-600); color: white;">
                                                <x-heroicon-o-play class="w-3 h-3" />
                                                Run Now
                                            </button>
                                            <span wire:loading wire:target="runTask" class="ml-2">
                                                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                </svg>
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Recent Execution Log --}}
        @php $logs = $this->getRecentLogs(); @endphp

        <div class="op-card overflow-hidden" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="px-6 py-4 flex items-center gap-3" style="border-bottom: 1px solid var(--color-border-subtle); background: var(--color-bg-inset);">
                <div class="p-1.5 rounded-lg" style="background: var(--color-bg-surface); color: var(--color-text-muted);">
                    <x-heroicon-o-document-text class="w-4 h-4" />
                </div>
                <h3 class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">
                    Recent Execution Log
                </h3>
            </div>

            <div class="p-6">
                @if($logs->isEmpty())
                    <div class="text-center py-12">
                        <x-heroicon-o-check-circle class="w-12 h-12 mx-auto mb-4" style="color: var(--color-success-500);" />
                        <p class="text-sm font-medium" style="color: var(--color-text-muted);">No execution logs found.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--color-border-subtle);">
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Task</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Status</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Duration</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Ran At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($logs as $log)
                                    <tr style="border-bottom: 1px solid var(--color-border-subtle);" class="op-table-row">
                                        <td class="px-4 py-3 font-mono text-xs" style="color: var(--color-text-primary);">
                                            {{ $log->job_name }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                                style="
                                                    @if($log->status->value === 'success')
                                                        background: rgba(34, 197, 94, 0.1); color: var(--color-success-500);
                                                    @elseif($log->status->value === 'failed')
                                                        background: rgba(239, 68, 68, 0.1); color: var(--color-danger-500);
                                                    @else
                                                        background: var(--color-bg-inset); color: var(--color-text-muted);
                                                    @endif
                                                ">
                                                {{ ucfirst($log->status->value) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-xs font-mono" style="color: var(--color-text-muted);">
                                            {{ $log->duration_ms ? $log->duration_ms . 'ms' : '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-xs font-mono" style="color: var(--color-text-muted);">
                                            {{ $log->ran_at?->diffForHumans() ?? '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
