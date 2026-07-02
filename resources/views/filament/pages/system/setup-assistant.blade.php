<x-filament-panels::page>
    <style>
    .op-action-btn:hover { border-color: var(--warning-300) !important; background: var(--color-bg-surface) !important; }
    .op-maintenance-btn:hover { border-color: var(--warning-300) !important; background: var(--color-bg-surface) !important; }
    </style>
    <div class="space-y-6">
        {{-- Header --}}
        <div class="op-card relative overflow-hidden p-6 page-header-gradient page-header-border">
            <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold tracking-tight flex items-center gap-2" style="color: var(--color-text-on-accent, #ffffff); font-family: var(--font-display);">
                        <x-heroicon-o-wrench-screwdriver class="w-5 h-5" style="color: var(--warning-500);" />
                        Setup Assistant
                    </h2>
                    <p class="mt-1 text-sm max-w-2xl leading-relaxed" style="color: var(--color-text-muted);">
                        Guided setup for your OeParts platform. Complete each step to ensure your system is production-ready.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    @if($this->isInstalled())
                        <div class="flex items-center gap-2 text-xs font-mono px-3 py-1.5 rounded-lg shrink-0 w-fit"
                            style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: var(--success-400);">
                            <span class="h-2 w-2 rounded-full animate-pulse" style="background: var(--success-500);"></span>
                            INSTALLED
                        </div>
                    @else
                        <div class="flex items-center gap-2 text-xs font-mono px-3 py-1.5 rounded-lg shrink-0 w-fit"
                            style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: var(--warning-400);">
                            <span class="h-2 w-2 rounded-full" style="background: var(--warning-500);"></span>
                            NOT INSTALLED
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Progress Bar --}}
        @php $progress = $this->getSetupProgress(); @endphp
        <div class="op-card p-6" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold" style="color: var(--color-text-primary); font-family: var(--font-display);">
                    Setup Progress
                </h3>
                <span class="text-lg font-black font-mono" style="color: {{ $progress >= 80 ? 'var(--success-500)' : ($progress >= 50 ? 'var(--warning-500)' : 'var(--danger-500)') }};">
                    {{ $progress }}%
                </span>
            </div>
            <div class="w-full h-3 rounded-full overflow-hidden" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                <div class="h-full rounded-full transition-all duration-700 ease-out"
                    style="width: {{ $progress }}%; background: {{ $progress >= 80 ? 'var(--success-500)' : ($progress >= 50 ? 'var(--warning-500)' : 'var(--danger-500)') }};">
                </div>
            </div>
            <p class="mt-2 text-xs font-mono" style="color: var(--color-text-muted);">
                {{ collect($this->getSetupSteps())->where('done', true)->count() }} of {{ count($this->getSetupSteps()) }} steps completed
            </p>
        </div>

        {{-- Setup Steps --}}
        <div class="op-card overflow-hidden" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="px-6 py-4 flex items-center gap-3" style="border-bottom: 1px solid var(--color-border-subtle); background: var(--color-bg-inset);">
                <div class="p-1.5 rounded-lg" style="background: var(--color-bg-surface); color: var(--color-text-muted);">
                    <x-heroicon-o-list-bullet class="w-4 h-4" />
                </div>
                <h3 class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">
                    Checklist
                </h3>
            </div>
            <div class="p-6">
                <div class="space-y-3">
                    @foreach($this->getSetupSteps() as $step)
                        <div class="flex items-center gap-4 py-3 px-4 rounded-xl transition-all duration-200"
                            style="border: 1px solid var(--color-border-subtle); background: {{ $step['done'] ? 'var(--color-bg-inset)' : 'var(--color-bg-surface)' }};">
                            {{-- Status Icon --}}
                            <div class="shrink-0">
                                @if($step['done'])
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center" style="background: var(--success-500); color: white;">
                                        <x-heroicon-o-check class="w-4 h-4" />
                                    </div>
                                @else
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center" style="background: var(--color-bg-inset); border: 2px solid var(--color-border-default); color: var(--color-text-muted);">
                                        <x-heroicon-o-minus class="w-4 h-4" />
                                    </div>
                                @endif
                            </div>

                            {{-- Label --}}
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-semibold" style="color: {{ $step['done'] ? 'var(--color-text-primary)' : 'var(--color-text-muted)' }}; font-family: var(--font-display);">
                                    {{ $step['label'] }}
                                </div>
                                <div class="text-xs font-mono" style="color: var(--color-text-muted);">
                                    {{ $step['description'] }}
                                </div>
                            </div>

                            {{-- Badge --}}
                            <div class="shrink-0">
                                @if($step['done'])
                                    <span class="text-xs font-bold font-mono px-2 py-1 rounded" style="background: var(--success-500); color: white;">
                                        DONE
                                    </span>
                                @else
                                    <span class="text-xs font-bold font-mono px-2 py-1 rounded" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-default); color: var(--color-text-muted);">
                                        PENDING
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Environment Details --}}
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
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    {{-- PHP Version --}}
                    <div class="p-4 rounded-xl" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                        <dt class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">PHP Version</dt>
                        <dd class="mt-2 text-sm font-bold font-mono" style="color: {{ version_compare($this->getPhpVersion(), '8.2', '>=') ? 'var(--success-500)' : 'var(--danger-500)' }};">
                            {{ $this->getPhpVersion() }}
                        </dd>
                    </div>

                    {{-- Database --}}
                    <div class="p-4 rounded-xl" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                        <dt class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Database</dt>
                        <dd class="mt-2 text-sm font-bold font-mono" style="color: {{ $this->getDbStatus()['ok'] ? 'var(--success-500)' : 'var(--danger-500)' }};">
                            {{ $this->getDbStatus()['message'] }}
                            @if($this->getDbStatus()['ok'])
                                <span class="text-xs font-normal" style="color: var(--color-text-muted);">({{ $this->getDbSize() }})</span>
                            @endif
                        </dd>
                    </div>

                    {{-- Cache --}}
                    <div class="p-4 rounded-xl" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                        <dt class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Cache Driver</dt>
                        <dd class="mt-2 text-sm font-bold font-mono" style="color: var(--color-text-primary);">
                            {{ $this->getCacheStatus() }}
                        </dd>
                    </div>

                    {{-- Queue --}}
                    <div class="p-4 rounded-xl" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                        <dt class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Queue Driver</dt>
                        <dd class="mt-2 text-sm font-bold font-mono" style="color: var(--color-text-primary);">
                            {{ $this->getQueueStatus() }}
                        </dd>
                    </div>

                    {{-- Redis --}}
                    <div class="p-4 rounded-xl" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                        <dt class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Redis</dt>
                        <dd class="mt-2 text-sm font-bold font-mono" style="color: {{ $this->getRedisStatus()['ok'] ? 'var(--success-500)' : 'var(--danger-500)' }};">
                            {{ $this->getRedisStatus()['message'] }}
                        </dd>
                    </div>

                    {{-- Failed Jobs --}}
                    <div class="p-4 rounded-xl" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                        <dt class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Failed Jobs</dt>
                        <dd class="mt-2 text-sm font-bold font-mono" style="color: {{ $this->getFailedJobsCount() > 0 ? 'var(--danger-500)' : 'var(--success-500)' }};">
                            {{ $this->getFailedJobsCount() }}
                        </dd>
                    </div>

                    {{-- Scheduler --}}
                    <div class="p-4 rounded-xl" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                        <dt class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Last Schedule Run</dt>
                        <dd class="mt-2 text-sm font-bold font-mono" style="color: var(--color-text-primary);">
                            {{ $this->getScheduleLastRun() }}
                        </dd>
                    </div>

                    {{-- Migrations --}}
                    <div class="p-4 rounded-xl" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                        <dt class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Migrations</dt>
                        <dd class="mt-2 text-xs font-mono leading-relaxed" style="color: var(--color-text-primary);">
                            {{ $this->getMigrationStatus() }}
                        </dd>
                    </div>
                </div>
            </div>
        </div>

        {{-- Maintenance Actions --}}
        <div class="op-card overflow-hidden" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="px-6 py-4 flex items-center gap-3" style="border-bottom: 1px solid var(--color-border-subtle); background: var(--color-bg-inset);">
                <div class="p-1.5 rounded-lg" style="background: var(--color-bg-surface); color: var(--color-text-muted);">
                    <x-heroicon-o-wrench-screwdriver class="w-4 h-4" />
                </div>
                <h3 class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">
                    Maintenance Actions
                </h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {{-- Run Migrations --}}
                    <button wire:click="runMigrations"
                        wire:confirm.prompt="This is a MEDIUM-RISK operation. Type MIGRATE to confirm:"
                        wire:loading
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50"
                        class="op-action-btn op-focus-ring op-press flex items-center gap-3 p-4 rounded-xl transition-all duration-200"
                        style="border: 1px solid var(--color-border-subtle); background: var(--color-bg-inset);">
                        <div class="p-2 rounded-lg" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-default); color: var(--color-text-muted);">
                            <x-heroicon-o-arrow-path class="w-5 h-5" />
                        </div>
                        <div class="text-left">
                            <div class="flex items-center gap-2">
                                <div class="text-sm font-bold" style="color: var(--color-text-primary); font-family: var(--font-display);">Run Migrations</div>
                                <span class="text-xs font-bold font-mono px-1.5 py-0.5 rounded" style="background: var(--warning-500); color: white;">MEDIUM</span>
                            </div>
                            <div class="text-xs font-mono" style="color: var(--color-text-muted);">Apply pending database changes</div>
                        </div>
                    </button>

                    {{-- Seed Demo Data --}}
                    <button wire:click="seedDemoData"
                        wire:confirm.prompt="HIGH-RISK: This will seed demo data. Type SEED to confirm:"
                        wire:loading
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50"
                        class="op-action-btn op-focus-ring op-press flex items-center gap-3 p-4 rounded-xl transition-all duration-200"
                        style="border: 1px solid var(--color-border-subtle); background: var(--color-bg-inset);">
                        <div class="p-2 rounded-lg" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-default); color: var(--color-text-muted);">
                            <x-heroicon-o-beaker class="w-5 h-5" />
                        </div>
                        <div class="text-left">
                            <div class="flex items-center gap-2">
                                <div class="text-sm font-bold" style="color: var(--color-text-primary); font-family: var(--font-display);">Seed Demo Data</div>
                                <span class="text-xs font-bold font-mono px-1.5 py-0.5 rounded" style="background: var(--danger-500); color: white;">HIGH</span>
                            </div>
                            <div class="text-xs font-mono" style="color: var(--color-text-muted);">Populate with sample products</div>
                        </div>
                    </button>

                    {{-- Toggle Maintenance --}}
                    <button wire:click="toggleMaintenance"
                        wire:confirm="MEDIUM-RISK: {{ $this->isDownForMaintenance() ? 'Bring the site back online. Are you sure?' : 'Block all public access to the site. Only admins can access. Are you sure?' }}"
                        wire:loading
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50"
                        class="op-maintenance-btn op-focus-ring op-press flex items-center gap-3 p-4 rounded-xl transition-all duration-200"
                        style="border: 1px solid {{ $this->isDownForMaintenance() ? 'var(--warning-300)' : 'var(--color-border-subtle)' }}; background: var(--color-bg-inset);"
                    >
                        <div class="p-2 rounded-lg" style="background: {{ $this->isDownForMaintenance() ? 'var(--warning-500)' : 'var(--color-bg-surface)' }}; border: 1px solid var(--color-border-default); color: {{ $this->isDownForMaintenance() ? 'white' : 'var(--color-text-muted)' }};">
                            <x-heroicon-o-power class="w-5 h-5" />
                        </div>
                        <div class="text-left">
                            <div class="flex items-center gap-2">
                                <div class="text-sm font-bold" style="color: var(--color-text-primary); font-family: var(--font-display);">
                                    {{ $this->isDownForMaintenance() ? 'Disable Maintenance' : 'Enable Maintenance' }}
                                </div>
                                <span class="text-xs font-bold font-mono px-1.5 py-0.5 rounded" style="background: var(--warning-500); color: white;">MEDIUM</span>
                            </div>
                            <div class="text-xs font-mono" style="color: var(--color-text-muted);">
                                {{ $this->isDownForMaintenance() ? 'Bring site back online' : 'Put site in maintenance mode' }}
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
