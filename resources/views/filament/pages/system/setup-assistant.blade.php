<x-filament::page>
    <div class="space-y-6">


        {{-- ═══ INSTALLATION STATUS ═══ --}}
        <x-filament::section icon="heroicon-o-cpu-chip">
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <span class="font-display font-semibold text-gray-900 dark:text-white">Installation & Environment Status</span>
                    @if($this->isInstalled())
                        <x-filament::badge color="success" size="sm" class="font-mono">Installed</x-filament::badge>
                    @else
                        <x-filament::badge color="warning" size="sm" class="font-mono">Not Installed</x-filament::badge>
                    @endif
                </div>
            </x-slot>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-2">
                <div class="bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/80 rounded-xl p-4 shadow-sm hover:shadow transition-all duration-200">
                    <dt class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider font-mono">Lock File</dt>
                    <dd class="mt-2 text-sm font-semibold text-gray-900 dark:text-white font-mono break-all">
                        @if($this->isInstalled())
                            {{ $this->getInstalledAt() }}
                        @else
                            <span class="text-warning-600 dark:text-warning-400">Not present</span>
                        @endif
                    </dd>
                </div>
                <div class="bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/80 rounded-xl p-4 shadow-sm hover:shadow transition-all duration-200">
                    <dt class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider font-mono">PHP Version</dt>
                    <dd class="mt-2 text-sm font-semibold text-gray-900 dark:text-white font-mono">{{ $this->getPhpVersion() }}</dd>
                </div>
                <div class="bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/80 rounded-xl p-4 shadow-sm hover:shadow transition-all duration-200">
                    <dt class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider font-mono">Database Status</dt>
                    <dd class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                        @if($this->getDbStatus()['ok'])
                            <span class="text-success-600 dark:text-success-400 font-bold">{{ $this->getDbStatus()['message'] }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400 font-mono">({{ $this->getDbSize() }})</span>
                        @else
                            <span class="text-danger-600 dark:text-danger-400 font-bold">{{ $this->getDbStatus()['message'] }}</span>
                        @endif
                    </dd>
                </div>
                <div class="bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/80 rounded-xl p-4 shadow-sm hover:shadow transition-all duration-200">
                    <dt class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider font-mono">Cache Driver</dt>
                    <dd class="mt-2 text-sm font-semibold text-gray-900 dark:text-white font-mono">{{ $this->getCacheStatus() }}</dd>
                </div>
                <div class="bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/80 rounded-xl p-4 shadow-sm hover:shadow transition-all duration-200">
                    <dt class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider font-mono">Redis Driver</dt>
                    <dd class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                        @if($this->getRedisStatus()['ok'])
                            <span class="text-success-600 dark:text-success-400 font-bold">{{ $this->getRedisStatus()['message'] }}</span>
                        @else
                            <span class="text-danger-600 dark:text-danger-400 font-bold">{{ $this->getRedisStatus()['message'] }}</span>
                        @endif
                    </dd>
                </div>
                <div class="bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/80 rounded-xl p-4 shadow-sm hover:shadow transition-all duration-200">
                    <dt class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider font-mono">Queue Status</dt>
                    <dd class="mt-2 text-sm font-semibold text-gray-900 dark:text-white font-mono">{{ $this->getQueueStatus() }}</dd>
                </div>
                <div class="bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/80 rounded-xl p-4 shadow-sm hover:shadow transition-all duration-200">
                    <dt class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider font-mono">Failed Queue Jobs</dt>
                    <dd class="mt-2 text-sm font-semibold text-gray-900 dark:text-white font-mono">
                        @if($this->getFailedJobsCount() > 0)
                            <span class="text-danger-600 dark:text-danger-400 font-black">{{ $this->getFailedJobsCount() }}</span>
                        @else
                            <span class="text-success-600 dark:text-success-400 font-bold">0</span>
                        @endif
                    </dd>
                </div>
                <div class="bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/80 rounded-xl p-4 shadow-sm hover:shadow transition-all duration-200">
                    <dt class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider font-mono">Maintenance Mode</dt>
                    <dd class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                        @if($this->isDownForMaintenance())
                            <x-filament::badge color="warning">Enabled</x-filament::badge>
                        @else
                            <x-filament::badge color="success">Disabled</x-filament::badge>
                        @endif
                    </dd>
                </div>
            </div>
        </x-filament::section>

        {{-- ═══ ACTIONS ═══ --}}
        <x-filament::section icon="heroicon-o-wrench-screwdriver">
            <x-slot name="heading">
                <span class="font-display font-semibold text-gray-900 dark:text-white">Maintenance & Operation Actions</span>
            </x-slot>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-2">
                <x-filament::button
                    wire:click="runMigrations"
                    color="primary"
                    icon="heroicon-o-arrow-path"
                    class="w-full shadow-sm hover:shadow transition duration-150">
                    Run Database Migrations
                </x-filament::button>

                <x-filament::button
                    wire:click="seedDemoData"
                    color="danger"
                    icon="heroicon-o-beaker"
                    class="w-full shadow-sm hover:shadow transition duration-150"
                    wire:confirm="This will seed demo data. Continue?">
                    Seed Demo & Mock Data
                </x-filament::button>

                <x-filament::button
                    wire:click="toggleMaintenance"
                    color="{{ $this->isDownForMaintenance() ? 'success' : 'warning' }}"
                    icon="heroicon-o-power"
                    class="w-full shadow-sm hover:shadow transition duration-150">
                    {{ $this->isDownForMaintenance() ? 'Disable Maintenance Mode' : 'Enable Maintenance Mode' }}
                </x-filament::button>
            </div>
            <div class="mt-4 text-xs font-mono text-gray-500 dark:text-gray-400 flex items-center gap-2">
                <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400" />
                <span>Migration Status: <strong class="text-gray-700 dark:text-gray-300">{{ $this->getMigrationStatus() }}</strong></span>
            </div>
        </x-filament::section>

        {{-- ═══ SCHEDULER STATUS ═══ --}}
        <x-filament::section icon="heroicon-o-clock">
            <x-slot name="heading">
                <span class="font-display font-semibold text-gray-900 dark:text-white">Task Scheduler Status</span>
            </x-slot>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                <div class="bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/80 rounded-xl p-4 shadow-sm">
                    <dt class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider font-mono">Last Scheduler Run</dt>
                    <dd class="mt-2 text-sm font-semibold text-gray-900 dark:text-white font-mono">{{ $this->getScheduleLastRun() }}</dd>
                </div>
            </div>
        </x-filament::section>

    </div>
</x-filament::page>
