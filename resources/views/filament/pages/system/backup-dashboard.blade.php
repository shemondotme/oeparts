<x-filament-panels::page>
    @if($runningBackupId)
        <div wire:poll.2s="pollBackup" class="fi-section rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900 mb-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-gray-950 dark:text-white">
                    Backup #{{ $runningBackupId }} running&hellip;
                    @if(!empty($backupProgress['stage']))
                        <span class="text-gray-500 dark:text-gray-400 font-normal">({{ $backupProgress['message'] ?? $backupProgress['stage'] }})</span>
                    @endif
                </span>
                <span class="text-sm font-mono text-gray-500 dark:text-gray-400">{{ $backupProgress['percent'] ?? 0 }}%</span>
            </div>
            <div class="h-2 w-full rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                <div
                    class="h-2 rounded-full bg-primary-600 transition-all duration-500 ease-out"
                    style="width: {{ max(2, $backupProgress['percent'] ?? 0) }}%"
                ></div>
            </div>
        </div>
    @endif
    {{ $this->table }}
</x-filament-panels::page>
