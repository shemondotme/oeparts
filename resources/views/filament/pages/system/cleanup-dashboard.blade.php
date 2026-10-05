<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Dev-only files left behind by an interrupted git-managed update --}}
        <div class="op-card p-6" style="background: var(--color-bg-surface, #ffffff); border: 1px solid var(--color-border-subtle, #e5e7eb);">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-1">
                <div class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted, #6b7280);">
                    Dev-Only Files
                </div>
                <button wire:click="scanDevFiles" wire:loading.attr="disabled" wire:target="scanDevFiles"
                    class="op-focus-ring op-press inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-bold uppercase tracking-wider"
                    style="border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-muted, #6b7280);">
                    <x-heroicon-o-arrow-path class="w-3.5 h-3.5" wire:loading.remove wire:target="scanDevFiles" />
                    <svg wire:loading wire:target="scanDevFiles" class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Scan
                </button>
            </div>

            @if(! $this->isGitManaged())
                <p class="text-sm" style="color: var(--color-text-muted, #6b7280);">
                    This install is not git-managed (no <code>.git</code> directory) — a release zip never ships these
                    files in the first place, so there's nothing for this section to find.
                </p>
            @else
                <p class="text-sm mb-4" style="color: var(--color-text-muted, #6b7280);">
                    Files a release never intends to ship on a live install (<code>tests/</code>, <code>phpstan*.neon</code>,
                    <code>compose.yaml</code>, <code>docker/</code>, …) — normally stripped automatically as the last step of
                    every update. A request killed from outside PHP partway through an update can leave some of these
                    behind. <code>.env</code>, <code>storage/</code> and <code>.git</code> are never touched by this.
                </p>

                @if($devFilesPreview === null)
                    <p class="text-sm" style="color: var(--color-text-muted, #6b7280);">Click Scan to check.</p>
                @elseif($devFilesPreview === [])
                    <div class="flex items-center gap-2 text-sm" style="color: var(--success-600, #16a34a);">
                        <x-heroicon-o-check-circle class="w-4 h-4" />
                        Clean — nothing to remove.
                    </div>
                @else
                    <div class="mb-4 p-3 rounded-xl text-sm" style="background: rgba(245, 158, 11, 0.08); border: 1px solid var(--warning-500, #f59e0b);">
                        <div class="text-xs font-bold uppercase tracking-wider mb-1" style="color: var(--warning-500, #f59e0b);">
                            {{ count($devFilesPreview) }} path(s) found
                        </div>
                        <ul class="list-disc pl-4 space-y-0.5 font-mono" style="color: var(--color-text-primary, #111827);">
                            @foreach($devFilesPreview as $path)
                                <li>{{ $path }}</li>
                            @endforeach
                        </ul>
                    </div>

                    <button wire:click="removeDevFiles" wire:loading.attr="disabled" wire:target="removeDevFiles"
                        x-data
                        x-on:click="if (!confirm('Remove these {{ count($devFilesPreview) }} dev-only path(s) from the live install? This only touches the exact paths listed above.')) $event.preventDefault()"
                        class="op-focus-ring op-press inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider"
                        style="background: var(--danger-500, #dc2626); color: white;">
                        <x-heroicon-o-trash class="w-3.5 h-3.5" />
                        Remove Now
                    </button>
                @endif

                @if($devFilesScannedAt)
                    <p class="mt-3 text-xs" style="color: var(--color-text-muted, #9ca3af);">
                        Last scanned {{ \Illuminate\Support\Carbon::parse($devFilesScannedAt)->diffForHumans() }}
                    </p>
                @endif
            @endif
        </div>

        {{-- Database schema audit — report only, never deletes anything --}}
        <div class="op-card p-6" style="background: var(--color-bg-surface, #ffffff); border: 1px solid var(--color-border-subtle, #e5e7eb);">
            <div class="text-xs font-bold uppercase tracking-widest font-mono mb-1" style="color: var(--color-text-muted, #6b7280);">
                Database Schema Audit
            </div>
            <p class="text-sm mb-4" style="color: var(--color-text-muted, #6b7280);">
                Compares the live database against what this install's own migration history says should exist.
                <strong style="color: var(--color-text-primary, #111827);">Report only — nothing here ever deletes a
                table or a row.</strong> Migrations only ever add, so under normal operation this should always come
                back clean; anything listed is worth a human look (a table created by hand, a leftover from
                something else, …), not something to act on automatically.
            </p>

            @if($schemaAudit === null)
                <p class="text-sm" style="color: var(--color-text-muted, #6b7280);">Click "Run Database Audit" above to check.</p>
            @else
                @php $findings = count($schemaAudit['unexpected']) + count($schemaAudit['expected_missing']); @endphp

                @if($findings === 0)
                    <div class="flex items-center gap-2 text-sm" style="color: var(--success-600, #16a34a);">
                        <x-heroicon-o-check-circle class="w-4 h-4" />
                        Clean — the live database exactly matches the migration history.
                    </div>
                @else
                    @if($schemaAudit['unexpected'] !== [])
                        <div class="mb-3 p-3 rounded-xl text-sm" style="background: rgba(245, 158, 11, 0.08); border: 1px solid var(--warning-500, #f59e0b);">
                            <div class="text-xs font-bold uppercase tracking-wider mb-1" style="color: var(--warning-500, #f59e0b);">
                                {{ count($schemaAudit['unexpected']) }} table(s) not accounted for by any migration
                            </div>
                            <ul class="list-disc pl-4 space-y-0.5 font-mono" style="color: var(--color-text-primary, #111827);">
                                @foreach($schemaAudit['unexpected'] as $table)
                                    <li>{{ $table }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($schemaAudit['expected_missing'] !== [])
                        <div class="mb-3 p-3 rounded-xl text-sm" style="background: rgba(220, 38, 38, 0.08); border: 1px solid var(--danger-500, #dc2626);">
                            <div class="text-xs font-bold uppercase tracking-wider mb-1" style="color: var(--danger-500, #dc2626);">
                                {{ count($schemaAudit['expected_missing']) }} table(s) a migration created but that no longer exist
                            </div>
                            <ul class="list-disc pl-4 space-y-0.5 font-mono" style="color: var(--color-text-primary, #111827);">
                                @foreach($schemaAudit['expected_missing'] as $table)
                                    <li>{{ $table }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endif

                @if($schemaAuditedAt)
                    <p class="mt-1 text-xs" style="color: var(--color-text-muted, #9ca3af);">
                        Last audited {{ \Illuminate\Support\Carbon::parse($schemaAuditedAt)->diffForHumans() }}
                    </p>
                @endif
            @endif
        </div>

        {{-- Scheduled maintenance tasks already exist and already run on their own
             schedule (OTP/cart/log/invoice-cache/refund-image cleanup, stale
             backup/update reclaim) — point at the page that already lists every
             one of them with its own "Run Now", rather than duplicating it. --}}
        <div class="op-card p-6" style="background: var(--color-bg-surface, #ffffff); border: 1px solid var(--color-border-subtle, #e5e7eb);">
            <div class="text-xs font-bold uppercase tracking-widest font-mono mb-1" style="color: var(--color-text-muted, #6b7280);">
                Scheduled Maintenance Tasks
            </div>
            <p class="text-sm mb-3" style="color: var(--color-text-muted, #6b7280);">
                Expired OTPs, abandoned carts, old logs, cached invoices, long-resolved refund images, and abandoned
                backup/update runs are all cleaned up automatically on their own schedule. To run any of them on
                demand instead of waiting:
            </p>
            <a href="{{ \App\Filament\Pages\System\ScheduledTasksPage::getUrl() }}"
                class="op-focus-ring op-press inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider"
                style="background: var(--primary-600, #2563eb); color: white;">
                <x-heroicon-o-clock class="w-3.5 h-3.5" />
                Open Scheduled Tasks
            </a>
        </div>
    </div>
</x-filament-panels::page>
