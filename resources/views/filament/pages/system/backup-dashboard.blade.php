<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header --}}
        <div class="op-card relative overflow-hidden p-6 page-header-gradient page-header-border">
            <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold tracking-tight flex items-center gap-2" style="color: var(--color-text-on-accent, #ffffff); font-family: var(--font-display);">
                        <x-heroicon-o-archive-box class="w-5 h-5" style="color: var(--color-warning-500);" />
                        Backup Management
                    </h2>
                    <p class="mt-1 text-sm max-w-2xl leading-relaxed" style="color: var(--color-text-muted);">
                        Create, download, and manage application backups. Backups include database, environment, config, and migration files.
                    </p>
                </div>
                <button wire:click="createBackup"
                    x-data
                    x-on:click="if (!confirm('Create a new backup now?')) $event.preventDefault()"
                    class="op-focus-ring op-press inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-all duration-200"
                    style="background: var(--color-success-600); color: white;">
                    <x-heroicon-o-arrow-down-tray class="w-3.5 h-3.5" />
                    Create Backup
                </button>
                <span wire:loading wire:target="createBackup" class="ml-2">
                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </span>
            </div>
        </div>

        {{-- Stats Cards --}}
        @php $stats = $this->getBackupStats(); @endphp

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="op-card op-hover-lift op-press relative overflow-hidden p-5 transition-all duration-300"
                style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
                <div class="relative flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="p-1.5 rounded-lg" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                                <x-heroicon-o-archive-box class="w-4 h-4" style="color: var(--color-primary-500);" />
                            </div>
                            <span class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Total Backups</span>
                        </div>
                        <div class="text-3xl font-bold font-mono" style="color: var(--color-text-primary); font-family: var(--font-display);">
                            {{ $stats['total_backups'] }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="op-card op-hover-lift op-press relative overflow-hidden p-5 transition-all duration-300"
                style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
                <div class="relative flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="p-1.5 rounded-lg" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                                <x-heroicon-o-server-stack class="w-4 h-4" style="color: var(--color-info-500);" />
                            </div>
                            <span class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Total Size</span>
                        </div>
                        <div class="text-2xl font-bold font-mono" style="color: var(--color-text-primary); font-family: var(--font-display);">
                            {{ $stats['total_size'] }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="op-card op-hover-lift op-press relative overflow-hidden p-5 transition-all duration-300"
                style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
                <div class="relative flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="p-1.5 rounded-lg" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                                <x-heroicon-o-clock class="w-4 h-4" style="color: var(--color-warning-500);" />
                            </div>
                            <span class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Last Backup</span>
                        </div>
                        <div class="text-lg font-bold font-mono" style="color: var(--color-text-primary); font-family: var(--font-display);">
                            {{ $stats['last_backup'] }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="op-card op-hover-lift op-press relative overflow-hidden p-5 transition-all duration-300"
                style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
                <div class="relative flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="p-1.5 rounded-lg" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                                <x-heroicon-o-document-text class="w-4 h-4" style="color: var(--color-success-500);" />
                            </div>
                            <span class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Latest File</span>
                        </div>
                        <div class="text-sm font-mono truncate" style="color: var(--color-text-primary);">
                            {{ $stats['last_backup_name'] }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Backups Table --}}
        <div class="op-card overflow-hidden" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="px-6 py-4 flex items-center gap-3" style="border-bottom: 1px solid var(--color-border-subtle); background: var(--color-bg-inset);">
                <div class="p-1.5 rounded-lg" style="background: var(--color-bg-surface); color: var(--color-text-muted);">
                    <x-heroicon-o-list-bullet class="w-4 h-4" />
                </div>
                <h3 class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">
                    Backup Files
                </h3>
            </div>

            <div class="p-6">
                @php $backups = $this->getBackups(); @endphp

                @if(empty($backups))
                    <div class="text-center py-12">
                        <x-heroicon-o-archive-box class="w-12 h-12 mx-auto mb-4" style="color: var(--color-text-muted);" />
                        <p class="text-sm font-medium" style="color: var(--color-text-muted);">No backups found. Create your first backup above.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--color-border-subtle);">
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Filename</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Size</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Created</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($backups as $backup)
                                    <tr style="border-bottom: 1px solid var(--color-border-subtle);" class="op-table-row">
                                        <td class="px-4 py-3 font-mono text-xs" style="color: var(--color-text-primary);">
                                            {{ $backup['name'] }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium font-mono"
                                                style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle); color: var(--color-text-secondary);">
                                                {{ $backup['size'] }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-xs font-mono" style="color: var(--color-text-muted);">
                                            {{ $backup['created_diff'] }}
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="{{ route('admin.backup.download', $backup['name']) }}"
                                                    class="op-focus-ring op-press inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold transition-all duration-200"
                                                    style="background: var(--color-success-600); color: white;">
                                                    <x-heroicon-o-arrow-down-tray class="w-3 h-3" />
                                                    Download
                                                </a>
                                                <button wire:click="deleteBackup(@js($backup['name']))"
                                                    x-data
                                                    x-on:click="if (!confirm('Delete this backup? This cannot be undone.')) $event.preventDefault()"
                                                    class="op-focus-ring op-press inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold transition-all duration-200"
                                                    style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle); color: var(--color-text-muted);">
                                                    <x-heroicon-o-trash class="w-3 h-3" />
                                                    Delete
                                                </button>
                                                <span wire:loading wire:target="deleteBackup" class="ml-2">
                                                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                    </svg>
                                                </span>
                                            </div>
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
