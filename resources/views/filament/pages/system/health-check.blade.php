<x-filament-panels::page>
    <style>
    .health-card:hover { border-color: var(--color-accent-300) !important; }
    </style>
    <div class="space-y-6">
        {{-- Header --}}
        <div class="op-card relative overflow-hidden p-6 page-header-gradient page-header-border">
            <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold tracking-tight flex items-center gap-2" style="color: var(--color-text-on-accent, #ffffff); font-family: var(--font-display);">
                        <x-heroicon-o-heart class="w-5 h-5" style="color: var(--color-warning-500);" />
                        System Health Monitor
                    </h2>
                    <p class="mt-1 text-sm max-w-2xl leading-relaxed" style="color: var(--color-text-muted);">
                        Real-time status of database, cache, queue, storage, scheduler, and compiled assets. Auto-refreshes every 30 seconds.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 text-xs font-mono px-3 py-1.5 rounded-lg shrink-0 w-fit"
                        style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: var(--color-success-400);">
                        <span class="h-2 w-2 rounded-full animate-pulse" style="background: var(--color-success-500);"></span>
                        POLLING 30s
                    </div>
                    <button wire:click="runCheckAction"
                        class="op-focus-ring op-press inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-all duration-200"
                        style="background: var(--color-brand-500); color: white;">
                        <x-heroicon-o-arrow-path class="w-3.5 h-3.5" />
                        Run Check
                    </button>
                </div>
            </div>
        </div>

        {{-- Status Cards --}}
        @php $results = $this->getHealthResults(); @endphp

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($results['checks'] as $key => $status)
                @php 
                    $statusInfo = $this->getStatusForCheck($status);
                    $remediation = $this->getRemediationForCheck($key);
                @endphp
                <div class="health-card op-card op-hover-lift op-press relative overflow-hidden p-5 transition-all duration-300"
                    style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
                    @if($status !== 'ok')
                        <div class="absolute inset-0 opacity-5" style="background: radial-gradient(circle at 50% 50%, {{ $status === 'fail' ? 'var(--color-danger-500)' : 'var(--color-warning-500)' }}, transparent 70%);"></div>
                    @endif

                    <div class="relative flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-3">
                                <div class="p-1.5 rounded-lg" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                                    @svg($statusInfo['icon'], 'w-4 h-4')
                                </div>
                                <dt class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">
                                    {{ ucfirst($key) }}
                                </dt>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-filament::badge
                                    :color="$statusInfo['color']"
                                    size="sm"
                                    class="font-mono font-bold"
                                >
                                    {{ strtoupper($status) }}
                                </x-filament::badge>
                                @if($statusInfo['pulse'])
                                    <span class="relative flex h-2 w-2">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75" style="background: var(--color-success-400);"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2" style="background: var(--color-success-500);"></span>
                                    </span>
                                @endif
                            </div>

                            {{-- Remediation CTA --}}
                            @if($status !== 'ok' && $remediation)
                                <div class="mt-4 pt-3" style="border-top: 1px solid var(--color-border-subtle);">
                                    @if($remediation['action'])
                                        <button 
                                            wire:click="{{ $remediation['action'] }}"
                                            @if($remediation['needsConfirmation'] ?? false)
                                                wire:confirm="{{ $remediation['confirmMessage'] ?? 'Are you sure?' }}"
                                            @endif
                                            wire:loading.attr="disabled"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold font-mono transition-all duration-200 hover:opacity-80"
                                            style="background: var(--color-{{ $remediation['color'] }}-500); color: white;">
                                            <x-dynamic-component :component="$remediation['icon']" class="w-3.5 h-3.5" />
                                            {{ $remediation['label'] }}
                                        </button>
                                    @elseif($remediation['externalUrl'] ?? null)
                                        <a href="{{ $remediation['externalUrl'] }}"
                                            target="_blank"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold font-mono transition-all duration-200 hover:opacity-80"
                                            style="background: var(--color-{{ $remediation['color'] }}-500); color: white;">
                                            <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5" />
                                            {{ $remediation['label'] }}
                                        </a>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <div class="flex items-end gap-0.5 h-6 opacity-40">
                            @for($i = 0; $i < 8; $i++)
                                <div class="w-0.5 rounded-full"
                                    style="background: {{ $status === 'ok' ? 'var(--color-success-400)' : ($status === 'fail' ? 'var(--color-danger-400)' : 'var(--color-warning-400)') }};"></div>
                            @endfor
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- System Info --}}
        <div class="op-card overflow-hidden" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="px-6 py-4 flex items-center gap-3" style="border-bottom: 1px solid var(--color-border-subtle); background: var(--color-bg-inset);">
                <div class="p-1.5 rounded-lg" style="background: var(--color-bg-surface); color: var(--color-text-muted);">
                    <x-heroicon-o-information-circle class="w-4 h-4" />
                </div>
                <h3 class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">
                    System Information
                </h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="p-4 rounded-xl" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                        <dt class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Version</dt>
                        <dd class="mt-2 text-sm font-bold font-mono" style="color: var(--color-text-primary);">{{ $results['version'] }}</dd>
                    </div>
                    <div class="p-4 rounded-xl" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                        <dt class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Last Check</dt>
                        <dd class="mt-2 text-sm font-bold font-mono" style="color: var(--color-text-primary);">{{ $results['timestamp'] }}</dd>
                    </div>
                    <div class="p-4 rounded-xl" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                        <dt class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Overall Status</dt>
                        <dd class="mt-2">
                            <x-filament::badge
                                :color="$results['status'] === 'ok' ? 'success' : 'warning'"
                                size="md"
                                class="font-mono font-bold"
                            >
                                {{ strtoupper($results['status']) }}
                            </x-filament::badge>
                        </dd>
                    </div>
                </div>
            </div>
        </div>

        {{-- Check History --}}
        @if(count($checkHistory) > 0)
            <div class="op-card overflow-hidden" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
                <div class="px-6 py-4 flex items-center gap-3" style="border-bottom: 1px solid var(--color-border-subtle); background: var(--color-bg-inset);">
                    <div class="p-1.5 rounded-lg" style="background: var(--color-bg-surface); color: var(--color-text-muted);">
                        <x-heroicon-o-clock class="w-4 h-4" />
                    </div>
                    <h3 class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">
                        Recent Checks
                    </h3>
                    <span class="text-xs font-mono ml-auto" style="color: var(--color-text-muted);">
                        {{ count($checkHistory) }} recorded
                    </span>
                </div>
                <div class="p-6">
                    <div class="space-y-2">
                        @foreach(array_reverse($checkHistory) as $i => $entry)
                            <div class="flex items-center gap-3 py-2 px-3 rounded-lg transition-colors duration-200"
                                style="border: 1px solid var(--color-border-subtle); {{ $i === 0 ? 'background: var(--color-bg-inset);' : '' }}">
                                <span class="w-1.5 h-1.5 rounded-full shrink-0"
                                    style="background: {{ $entry['status'] === 'ok' ? 'var(--color-success-500)' : ($entry['status'] === 'degraded' ? 'var(--color-warning-500)' : 'var(--color-danger-500)') }};"></span>
                                <span class="text-xs font-mono" style="color: var(--color-text-muted);">{{ $entry['time'] }}</span>
                                <span class="text-xs font-bold font-mono uppercase"
                                    style="color: {{ $entry['status'] === 'ok' ? 'var(--color-success-500)' : ($entry['status'] === 'degraded' ? 'var(--color-warning-500)' : 'var(--color-danger-500)') }};">
                                    {{ $entry['status'] }}
                                </span>
                                <span class="text-xs font-mono ml-auto" style="color: var(--color-text-muted);">
                                    @foreach($entry['checks'] as $name => $val)
                                        <span class="inline-flex items-center gap-0.5 mr-2">
                                            {{ $name }}:
                                            <span class="font-bold" style="color: {{ $val === 'ok' ? 'var(--color-success-500)' : ($val === 'fail' ? 'var(--color-danger-500)' : 'var(--color-warning-500)') }};">{{ $val }}</span>
                                        </span>
                                    @endforeach
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
