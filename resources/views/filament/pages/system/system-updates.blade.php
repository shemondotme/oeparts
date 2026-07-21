<x-filament-panels::page>
    @php
        $s = $this->status ?? [];
        $reachable = $s['reachable'] ?? false;
        $available = $s['update_available'] ?? false;
        $security  = $s['security'] ?? false;

        if (! $reachable) {
            $tone = ['color' => 'var(--color-text-muted, #6b7280)', 'label' => 'Update server unreachable', 'icon' => 'heroicon-o-signal-slash'];
        } elseif ($available && $security) {
            $tone = ['color' => 'var(--danger-500, #dc2626)', 'label' => 'Security update available', 'icon' => 'heroicon-o-shield-exclamation'];
        } elseif ($available) {
            $tone = ['color' => 'var(--warning-500, #f59e0b)', 'label' => 'Update available', 'icon' => 'heroicon-o-arrow-up-circle'];
        } else {
            $tone = ['color' => 'var(--success-600, #16a34a)', 'label' => 'Up to date', 'icon' => 'heroicon-o-check-circle'];
        }
    @endphp

    <div class="space-y-6">
        {{-- Header --}}
        <div class="op-card relative overflow-hidden p-6 page-header-gradient page-header-border">
            <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold tracking-tight flex items-center gap-2"
                        style="color: var(--color-text-on-accent, #ffffff); font-family: var(--font-display);">
                        <x-heroicon-o-arrow-up-circle class="w-5 h-5" style="color: var(--warning-500, #f59e0b);" />
                        System Updates
                    </h2>
                    <p class="mt-1 text-sm max-w-2xl leading-relaxed" style="color: var(--color-text-muted-on-accent, rgba(228, 228, 231, 0.72));">
                        Check for new OeParts releases and review the changelog. When an update is
                        available, apply it with one click below — a full backup runs first, and it's
                        verified automatically.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button wire:click="checkNow"
                        wire:loading.attr="disabled"
                        class="op-focus-ring op-press inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-all duration-200"
                        style="background: var(--primary-600, #2563eb); color: white;">
                        <x-heroicon-o-arrow-path class="w-3.5 h-3.5" wire:loading.remove wire:target="checkNow" />
                        <svg wire:loading wire:target="checkNow" class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Check now
                    </button>
                </div>
            </div>
        </div>

        {{-- Status hero --}}
        <div class="op-card relative overflow-hidden p-6"
            style="background: var(--color-bg-surface, #ffffff); border: 1px solid var(--color-border-subtle, #e5e7eb);">
            <div class="flex flex-col sm:flex-row sm:items-center gap-5">
                <div class="p-3 rounded-2xl shrink-0"
                    style="background: var(--color-bg-inset, #f3f4f6); border: 1px solid var(--color-border-subtle, #e5e7eb);">
                    <x-dynamic-component :component="$tone['icon']" class="w-8 h-8" :style="'color: ' . $tone['color']" />
                </div>
                <div class="flex-1">
                    <div class="text-xl font-bold" style="color: {{ $tone['color'] }}; font-family: var(--font-display);">
                        {{ $tone['label'] }}
                    </div>
                    <div class="mt-1 flex flex-wrap items-center gap-x-6 gap-y-1 text-sm font-mono" style="color: var(--color-text-muted, #6b7280);">
                        <span>Installed: <strong style="color: var(--color-text-primary, #111827);">{{ $s['current_version'] ?? 'unknown' }}</strong></span>
                        @if($reachable)
                            <span>Latest: <strong style="color: var(--color-text-primary, #111827);">{{ $s['latest_version'] ?? 'unknown' }}</strong></span>
                        @endif
                        <span>Channel: {{ $s['channel'] ?? 'stable' }}</span>
                    </div>
                </div>
            </div>

            @if(! $reachable && ! empty($s['error']))
                <p class="mt-4 text-sm" style="color: var(--color-text-muted, #6b7280);">{{ $s['error'] }}</p>
            @endif
        </div>

        {{-- One-click apply (Chunk 3.5) — the primary action, shown first so it's not
             mistaken for a "download only" page. Falls back gracefully: an admin
             without the "apply updates" permission still sees the changelog/download
             card below. --}}
        @if($this->canApply() && ($available || $applying))
            <div class="op-card relative overflow-hidden p-6"
                style="background: var(--color-bg-surface, #ffffff); border: 1px solid {{ $security ? 'var(--danger-500, #dc2626)' : 'var(--color-border-subtle, #e5e7eb)' }};"
                x-data
                x-on:update-complete.window="setTimeout(() => window.location.reload(), 1500)">

                @if(! $applying && empty($applyPreview))
                    <div class="text-sm font-bold uppercase tracking-widest font-mono mb-3" style="color: var(--color-text-muted, #6b7280);">
                        Apply this update
                    </div>
                    <p class="text-sm mb-4" style="color: var(--color-text-muted, #6b7280);">
                        Review exactly what will happen before updating to {{ $s['latest_version'] ?? 'the latest version' }} —
                        a full backup is taken first, the site enters maintenance mode, and the update is applied and
                        verified automatically.
                    </p>
                    <button wire:click="loadPreview" wire:loading.attr="disabled" wire:target="loadPreview"
                        class="op-focus-ring op-press inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider"
                        style="background: {{ $security ? 'var(--danger-500, #dc2626)' : 'var(--primary-600, #2563eb)' }}; color: white;">
                        <x-heroicon-o-rocket-launch class="w-3.5 h-3.5" />
                        Review &amp; apply update
                    </button>
                @elseif(! $applying)
                    @php
                        $p = $applyPreview;
                        $preflight = $p['preflight'] ?? ['checks' => [], 'can_proceed' => true, 'has_warnings' => false];
                        $mins = (int) ceil(($p['eta_seconds'] ?? 0) / 60);
                    @endphp
                    <div class="text-sm font-bold uppercase tracking-widest font-mono mb-3" style="color: var(--color-text-muted, #6b7280);">
                        Confirm update: {{ $p['from_version'] ?? '?' }} &rarr; {{ $p['to_version'] ?? '?' }}
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                        <div class="p-3 rounded-xl" style="background: var(--color-bg-inset, #f3f4f6);">
                            <div class="text-[10px] font-bold uppercase tracking-wider" style="color: var(--color-text-muted, #6b7280);">Download size</div>
                            <div class="text-sm font-mono font-bold" style="color: var(--color-text-primary, #111827);">
                                {{ $p['size_bytes'] ? round($p['size_bytes'] / 1048576, 1).' MB' : '—' }}
                            </div>
                        </div>
                        <div class="p-3 rounded-xl" style="background: var(--color-bg-inset, #f3f4f6);">
                            <div class="text-[10px] font-bold uppercase tracking-wider" style="color: var(--color-text-muted, #6b7280);">Migrations</div>
                            <div class="text-sm font-mono font-bold" style="color: var(--color-text-primary, #111827);">{{ $p['migration_count'] ?? 0 }}</div>
                        </div>
                        <div class="p-3 rounded-xl" style="background: var(--color-bg-inset, #f3f4f6);">
                            <div class="text-[10px] font-bold uppercase tracking-wider" style="color: var(--color-text-muted, #6b7280);">Est. time</div>
                            <div class="text-sm font-mono font-bold" style="color: var(--color-text-primary, #111827);">~{{ max(1, $mins) }} min</div>
                        </div>
                        <div class="p-3 rounded-xl" style="background: var(--color-bg-inset, #f3f4f6);">
                            <div class="text-[10px] font-bold uppercase tracking-wider" style="color: var(--color-text-muted, #6b7280);">Pre-flight</div>
                            <div class="text-sm font-mono font-bold" style="color: {{ $preflight['can_proceed'] ? (($preflight['has_warnings'] ?? false) ? 'var(--warning-500, #f59e0b)' : 'var(--success-600, #16a34a)') : 'var(--danger-500, #dc2626)' }};">
                                {{ $preflight['failure_count'] ?? 0 }} fail / {{ $preflight['warning_count'] ?? 0 }} warn
                            </div>
                        </div>
                    </div>

                    @if(!empty($p['breaking_changes']))
                        <div class="mb-4 p-3 rounded-xl text-sm" style="background: rgba(220, 38, 38, 0.08); border: 1px solid var(--danger-500, #dc2626);">
                            <div class="text-xs font-bold uppercase tracking-wider mb-1" style="color: var(--danger-500, #dc2626);">Breaking changes</div>
                            <ul class="list-disc pl-4 space-y-0.5" style="color: var(--color-text-primary, #111827);">
                                @foreach($p['breaking_changes'] as $change)
                                    <li>{{ $change }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(!empty($p['pre_update_notes']))
                        <div class="mb-4 p-3 rounded-xl text-sm" style="background: var(--color-bg-inset, #f3f4f6); color: var(--color-text-muted, #6b7280);">
                            {{ $p['pre_update_notes'] }}
                        </div>
                    @endif

                    @if(!empty($preflight['checks']))
                        <div class="mb-4 space-y-1.5">
                            @foreach($preflight['checks'] as $check)
                                @continue($check['status'] === 'pass')
                                <div class="flex items-start gap-2 p-2 rounded-lg text-xs"
                                    style="background: {{ $check['status'] === 'fail' ? 'rgba(220, 38, 38, 0.08)' : 'rgba(245, 158, 11, 0.08)' }};">
                                    <x-dynamic-component :component="$check['status'] === 'fail' ? 'heroicon-o-x-circle' : 'heroicon-o-exclamation-triangle'"
                                        class="w-4 h-4 shrink-0 mt-0.5"
                                        style="color: {{ $check['status'] === 'fail' ? 'var(--danger-500, #dc2626)' : 'var(--warning-500, #f59e0b)' }};" />
                                    <span style="color: var(--color-text-primary, #111827);"><strong>{{ $check['label'] }}:</strong> {{ $check['message'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($preflight['can_proceed'] ?? true)
                        <div class="flex flex-wrap items-end gap-3">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider mb-1" style="color: var(--color-text-muted, #6b7280);">Your password</label>
                                <input type="password" wire:model="applyPassword"
                                    class="op-focus-ring rounded-xl px-3 py-2 text-sm font-mono"
                                    style="background: var(--color-bg-inset, #f3f4f6); border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);" />
                                @error('applyPassword')<div class="mt-1 text-xs" style="color: var(--danger-500, #dc2626);">{{ $message }}</div>@enderror
                            </div>

                            @if($preflight['has_warnings'] ?? false)
                                <label class="flex items-center gap-2 text-xs mb-2" style="color: var(--color-text-primary, #111827);">
                                    <input type="checkbox" wire:model.live="previewAcknowledged" class="rounded" />
                                    I've reviewed the warnings above and want to proceed anyway
                                </label>
                            @endif

                            <button wire:click="startApply" wire:loading.attr="disabled" wire:target="startApply"
                                @disabled(($preflight['has_warnings'] ?? false) && ! $previewAcknowledged)
                                class="op-focus-ring op-press inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider disabled:opacity-40"
                                style="background: {{ $security ? 'var(--danger-500, #dc2626)' : 'var(--primary-600, #2563eb)' }}; color: white;">
                                <x-heroicon-o-rocket-launch class="w-3.5 h-3.5" />
                                Confirm &amp; apply
                            </button>
                            <button wire:click="cancelPreview" type="button"
                                class="op-focus-ring inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider"
                                style="border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-muted, #6b7280);">
                                Cancel
                            </button>
                        </div>
                    @else
                        <div class="flex items-center gap-3">
                            <p class="text-xs" style="color: var(--danger-500, #dc2626);">
                                Pre-flight checks are failing — resolve the issues above before applying.
                            </p>
                            <button wire:click="cancelPreview" type="button"
                                class="op-focus-ring inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider"
                                style="border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-muted, #6b7280);">
                                Close
                            </button>
                        </div>
                    @endif
                @else
                    @php
                        $steps = \App\Services\Updates\UpdateApplier::STEPS;
                        $stepLabels = [
                            'backup'   => 'Backing up database & files',
                            'download' => 'Downloading release',
                            'extract'  => 'Extracting release',
                            'swap'     => 'Swapping in new files',
                            'finalize' => 'Running migrations',
                            'verify'   => 'Verifying the update',
                        ];
                        $currentStep = $applyStatus['step'] ?? $steps[0];
                        $stepIndex = array_search($currentStep, $steps, true);
                        $stepIndex = $stepIndex === false ? count($steps) : $stepIndex;
                    @endphp
                    <div wire:poll.2s="pollApply">
                        <div class="flex items-center gap-3 mb-2">
                            <svg class="animate-spin h-4 w-4" style="color: var(--primary-600, #2563eb);" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span class="text-sm font-bold" style="color: var(--color-text-primary, #111827);">
                                {{ $stepLabels[$currentStep] ?? ucfirst((string) $currentStep) }}&hellip;
                                <span class="font-mono font-normal" style="color: var(--color-text-muted, #6b7280);">(step {{ min($stepIndex + 1, count($steps)) }} of {{ count($steps) }})</span>
                            </span>
                        </div>
                        <div class="h-1.5 w-full rounded-full overflow-hidden mb-3" style="background: var(--color-bg-inset, #f3f4f6);">
                            <div class="h-1.5 rounded-full transition-all duration-500 ease-out"
                                style="width: {{ max(4, (int) round((min($stepIndex + 1, count($steps)) / count($steps)) * 100)) }}%; background: var(--primary-600, #2563eb);"></div>
                        </div>
                        <p class="text-xs" style="color: var(--color-text-muted, #6b7280);">
                            Keep this window open — the page reloads automatically when the update finishes.
                            Long steps (backup, download) can take a while; the browser tab title won't change,
                            but no action is needed from you.
                        </p>
                    </div>
                @endif
            </div>
        @endif

        {{-- Update details / manual fallback (changelog + direct download, e.g. for an
             admin without "apply updates", or anyone who prefers a manual install) --}}
        @if($available)
            <div class="op-card relative overflow-hidden p-6"
                style="background: var(--color-bg-surface, #ffffff); border: 1px solid var(--color-border-subtle, #e5e7eb);">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-widest font-mono mb-1" style="color: var(--color-text-muted, #6b7280);">New Version</div>
                        <div class="text-lg font-bold font-mono" style="color: var(--color-text-primary, #111827);">{{ $s['latest_version'] }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-bold uppercase tracking-widest font-mono mb-1" style="color: var(--color-text-muted, #6b7280);">Released</div>
                        <div class="text-lg font-bold font-mono" style="color: var(--color-text-primary, #111827);">{{ $s['release_date'] ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-bold uppercase tracking-widest font-mono mb-1" style="color: var(--color-text-muted, #6b7280);">DB Migrations</div>
                        <div class="text-lg font-bold font-mono" style="color: var(--color-text-primary, #111827);">{{ $s['migration_count'] ?? 0 }}</div>
                    </div>
                </div>

                @if(!empty($s['upgrade_path']) && count($s['upgrade_path']) > 1)
                    <div class="mb-4 p-3 rounded-xl text-sm"
                        style="background: var(--color-bg-inset, #f3f4f6); color: var(--color-text-muted, #6b7280);">
                        This is a multi-step upgrade — apply in order:
                        <span class="font-mono" style="color: var(--color-text-primary, #111827);">{{ implode(' → ', $s['upgrade_path']) }}</span>
                    </div>
                @endif

                <div class="flex flex-wrap items-center gap-3">
                    @if(!empty($s['changelog_url']))
                        <a href="{{ $s['changelog_url'] }}" target="_blank" rel="noopener"
                           class="op-focus-ring inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider"
                           style="border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);">
                            <x-heroicon-o-document-text class="w-3.5 h-3.5" />
                            View changelog
                        </a>
                    @endif
                    @if(!empty($s['download_url']))
                        <a href="{{ $s['download_url'] }}" target="_blank" rel="noopener"
                           class="op-focus-ring inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider"
                           style="border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);">
                            <x-heroicon-o-arrow-down-tray class="w-3.5 h-3.5" />
                            Download release (manual install)
                        </a>
                    @endif
                </div>
            </div>
        @endif

        {{-- Footer meta --}}
        @if(!empty($s['checked_at']))
            <p class="text-xs font-mono" style="color: var(--color-text-muted, #6b7280);">
                Last checked: {{ \Illuminate\Support\Carbon::parse($s['checked_at'])->diffForHumans() }}
            </p>
        @endif
    </div>
</x-filament-panels::page>
