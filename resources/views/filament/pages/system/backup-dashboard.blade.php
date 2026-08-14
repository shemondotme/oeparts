<x-filament-panels::page>
    <div class="space-y-6">
        {{-- existing progress card — untouched --}}
        @if($runningBackupId)
            <div wire:poll.2s="pollBackup" class="fi-section rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
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

        {{-- Overview strip --}}
        @php $lastBackup = $this->overviewLastBackup(); $retention = $this->overviewRetentionPolicy(); @endphp
        <div class="op-tile-grid">
            <div class="op-tile">
                <div class="op-tile-label">Storage Used</div>
                <div class="op-tile-value">{{ $this->formatBytes($this->overviewStorageUsedBytes()) }}</div>
                <div class="op-tile-meta">Successful, un-pruned backups</div>
            </div>

            @php
                $lbTone = match ($lastBackup['status']) {
                    'ok' => 'op-status-pill-ok',
                    'stale' => 'op-status-pill-warn',
                    'none' => 'op-status-pill-down',
                    default => 'op-status-pill-muted',
                };
            @endphp
            <div class="op-tile">
                <div class="op-tile-label">Last Backup</div>
                <div class="op-tile-value">{{ $lastBackup['detail'] }}</div>
                <span class="op-status-pill {{ $lbTone }} op-tile-meta-pill">{{ ucfirst($lastBackup['status']) }}</span>
            </div>

            <div class="op-tile">
                <div class="op-tile-label">Retention Policy</div>
                <div class="op-tile-value" style="font-size:1rem;">
                    {{ $retention['daily'] }}d / {{ $retention['weekly'] }}w / {{ $retention['monthly'] }}m
                </div>
                <div class="op-tile-meta">Daily / weekly / monthly kept</div>
            </div>

            <div class="op-tile">
                <div class="op-tile-label">Encryption</div>
                <div class="op-tile-value" style="font-size:1rem; color: {{ $this->overviewEncryptionReady() ? 'var(--success-600, #16a34a)' : 'var(--danger-500, #dc2626)' }};">
                    {{ $this->overviewEncryptionReady() ? 'Key configured' : 'No key set' }}
                </div>
                <div class="op-tile-meta">AES-256-GCM, mandatory</div>
            </div>
        </div>

        {{-- Lock Status card — surfaces the shared Backup/Update lock's owner,
             age, and staleness; "Release stale lock" only ever appears for a
             CONFIRMED-stale lock, never a live one. --}}
        @php $lock = $this->lockStatus(); @endphp
        <div class="op-card p-5" style="background: var(--color-bg-surface, #ffffff); border: 1px solid var(--color-border-subtle, #e5e7eb);">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="op-dot {{ ($lock['locked'] ?? false) && ! ($lock['is_stale'] ?? false) ? 'op-dot-live' : '' }}"
                          style="{{ ($lock['locked'] ?? false) ? (($lock['is_stale'] ?? false) ? 'background: var(--danger-500, #ef4444);' : '') : 'background: rgba(120,120,130,0.45);' }}"></span>
                    <span class="text-sm font-bold" style="color: var(--color-text-primary, #111827);">Backup / Update Lock</span>
                    <span class="op-status-pill {{ ($lock['locked'] ?? false) ? (($lock['is_stale'] ?? false) ? 'op-status-pill-down' : 'op-status-pill-ok') : 'op-status-pill-muted' }}">
                        {{ ($lock['locked'] ?? false) ? (($lock['is_stale'] ?? false) ? 'Stale' : 'Held') : 'Free' }}
                    </span>
                </div>

                @if(($lock['locked'] ?? false) && ($lock['is_stale'] ?? false))
                    <button wire:click="releaseLock"
                        x-data
                        x-on:click="if (!confirm('This lock is confirmed stale (held {{ $lock['age_human'] }} by {{ $lock['owner'] }}) and is blocking backups/updates. Release it?')) $event.preventDefault()"
                        wire:loading.attr="disabled"
                        class="op-focus-ring op-press px-3 py-1.5 rounded-xl text-xs font-bold uppercase tracking-wider"
                        style="background: var(--danger-500, #dc2626); color: white;">
                        Release stale lock
                    </button>
                @endif
            </div>

            @if($lock['locked'] ?? false)
                <p class="mt-2 text-xs font-mono" style="color: var(--color-text-muted, #6b7280);">
                    Held by <strong style="color: var(--color-text-primary, #111827);">{{ $lock['owner'] }}</strong>
                    for {{ $lock['age_human'] }}
                    @if($lock['is_stale'] ?? false)
                        — past the {{ $settingsStaleAfterMinutes }}-minute stale threshold. This is blocking every backup AND update until released.
                    @endif
                </p>
            @else
                <p class="mt-2 text-xs" style="color: var(--color-text-muted, #6b7280);">No backup or update is currently running.</p>
            @endif
        </div>

        {{-- existing table — untouched --}}
        {{ $this->table }}

        {{-- Backup Settings panel — retention/schedule/stale-threshold,
             DB-editable via settings(). --}}
        @if($this->canManageBackups())
            <div class="op-card p-6" style="background: var(--color-bg-surface, #ffffff); border: 1px solid var(--color-border-subtle, #e5e7eb);">
                <div class="text-xs font-bold uppercase tracking-widest font-mono mb-4" style="color: var(--color-text-muted, #6b7280);">
                    Backup Settings
                </div>
                <div class="flex flex-wrap items-end gap-6">
                    <div>
                        <label class="text-xs font-bold uppercase tracking-widest font-mono mb-2 block" style="color: var(--color-text-muted, #6b7280);">Keep daily</label>
                        <input type="number" min="0" wire:model="settingsRetentionDaily"
                            class="w-24 px-3 py-2 text-sm rounded-xl"
                            style="background: var(--color-bg-inset, #f3f4f6); border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);" />
                    </div>
                    <div>
                        <label class="text-xs font-bold uppercase tracking-widest font-mono mb-2 block" style="color: var(--color-text-muted, #6b7280);">Keep weekly</label>
                        <input type="number" min="0" wire:model="settingsRetentionWeekly"
                            class="w-24 px-3 py-2 text-sm rounded-xl"
                            style="background: var(--color-bg-inset, #f3f4f6); border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);" />
                    </div>
                    <div>
                        <label class="text-xs font-bold uppercase tracking-widest font-mono mb-2 block" style="color: var(--color-text-muted, #6b7280);">Keep monthly</label>
                        <input type="number" min="0" wire:model="settingsRetentionMonthly"
                            class="w-24 px-3 py-2 text-sm rounded-xl"
                            style="background: var(--color-bg-inset, #f3f4f6); border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);" />
                    </div>
                    <div>
                        <label class="text-xs font-bold uppercase tracking-widest font-mono mb-2 block" style="color: var(--color-text-muted, #6b7280);">Scheduled backups</label>
                        <label class="flex items-center gap-2 px-3 py-2 text-sm rounded-xl cursor-pointer"
                            style="background: var(--color-bg-inset, #f3f4f6); border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);">
                            <input type="checkbox" wire:model="settingsScheduleEnabled" class="rounded" />
                            Enabled
                        </label>
                    </div>
                    <div>
                        <label class="text-xs font-bold uppercase tracking-widest font-mono mb-2 block" style="color: var(--color-text-muted, #6b7280);">Scheduled backup time</label>
                        <input type="time" wire:model="settingsScheduleTime"
                            class="px-3 py-2 text-sm rounded-xl"
                            style="background: var(--color-bg-inset, #f3f4f6); border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);" />
                    </div>
                    <div>
                        <label class="text-xs font-bold uppercase tracking-widest font-mono mb-2 block" style="color: var(--color-text-muted, #6b7280);">Stale lock threshold (min)</label>
                        <input type="number" min="1" wire:model="settingsStaleAfterMinutes"
                            class="w-24 px-3 py-2 text-sm rounded-xl"
                            style="background: var(--color-bg-inset, #f3f4f6); border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);" />
                    </div>
                    <button wire:click="saveBackupSettings" wire:loading.attr="disabled"
                        class="op-focus-ring op-press px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider"
                        style="background: var(--primary-600, #2563eb); color: white;">
                        Save
                    </button>
                </div>
                @error('settingsRetentionDaily')<div class="mt-2 text-xs" style="color: var(--danger-500, #dc2626);">{{ $message }}</div>@enderror
                @error('settingsRetentionWeekly')<div class="mt-2 text-xs" style="color: var(--danger-500, #dc2626);">{{ $message }}</div>@enderror
                @error('settingsRetentionMonthly')<div class="mt-2 text-xs" style="color: var(--danger-500, #dc2626);">{{ $message }}</div>@enderror
                @error('settingsScheduleTime')<div class="mt-2 text-xs" style="color: var(--danger-500, #dc2626);">{{ $message }}</div>@enderror
                @error('settingsStaleAfterMinutes')<div class="mt-2 text-xs" style="color: var(--danger-500, #dc2626);">{{ $message }}</div>@enderror
            </div>
        @endif
    </div>
</x-filament-panels::page>
