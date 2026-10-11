{{-- The two original sections, unchanged in behaviour: dev-only files (git installs) and the read-only schema audit. --}}
{{-- Dev-only files left behind by an interrupted git-managed update --}}
<div class="oc-card">
    <div class="oc-card-head">
        <div class="oc-h">Dev-only files</div>
        <button type="button" wire:click="scanDevFiles" wire:loading.attr="disabled" wire:target="scanDevFiles" class="oc-btn">
            <x-heroicon-o-arrow-path style="width:.9rem;height:.9rem;" wire:loading.remove wire:target="scanDevFiles" />
            <span wire:loading wire:target="scanDevFiles">Scanning…</span><span wire:loading.remove wire:target="scanDevFiles">Scan</span>
        </button>
    </div>

    @if(! $this->isGitManaged())
        <p class="oc-sub" style="margin:0;">
            This install is not git-managed (no <code>.git</code> directory) — a release zip never ships these
            files in the first place, so there's nothing for this section to find.
        </p>
    @else
        <p class="oc-sub">
            Files a release never intends to ship on a live install (<code>tests/</code>, <code>phpstan*.neon</code>,
            <code>compose.yaml</code>, <code>docker/</code>, …) — normally stripped automatically as the last step of
            every update. A request killed from outside PHP partway through an update can leave some of these
            behind. <code>.env</code>, <code>storage/</code> and <code>.git</code> are never touched by this.
        </p>

        @if($devFilesPreview === null)
            <p class="oc-muted">Click Scan to check.</p>
        @elseif($devFilesPreview === [])
            <div class="oc-note ok">Clean — nothing to remove.</div>
        @else
            <div class="oc-note warn" style="margin-bottom:1rem;">
                <div class="oc-h" style="color: var(--warning-500, #f59e0b);margin-bottom:.35rem;">{{ count($devFilesPreview) }} path(s) found</div>
                <ul style="list-style:disc;padding-left:1.1rem;margin:0;" class="oc-mono">
                    @foreach($devFilesPreview as $path)
                        <li>{{ $path }}</li>
                    @endforeach
                </ul>
            </div>

            <button type="button" wire:click="removeDevFiles" wire:loading.attr="disabled" wire:target="removeDevFiles"
                x-data
                x-on:click="if (!confirm('Remove these {{ count($devFilesPreview) }} dev-only path(s) from the live install? This only touches the exact paths listed above.')) $event.preventDefault()"
                class="oc-btn oc-btn-danger">
                <x-heroicon-o-trash style="width:.9rem;height:.9rem;" /> Remove now
            </button>
        @endif

        @if($devFilesScannedAt)
            <p class="oc-muted" style="margin-top:.75rem;">Last scanned {{ \Illuminate\Support\Carbon::parse($devFilesScannedAt)->diffForHumans() }}</p>
        @endif
    @endif
</div>

{{-- Database schema audit — report only, never deletes anything --}}
<div class="oc-card">
    <div class="oc-card-head"><div class="oc-h">Database schema audit</div></div>
    <p class="oc-sub">
        Compares the live database against what this install's own migration history says should exist.
        <strong class="oc-strong">Report only — nothing here ever deletes a table or a row.</strong> Migrations only ever add, so under normal
        operation this should always come back clean; anything listed is worth a human look (a table created by hand, a leftover from
        something else, …), not something to act on automatically.
    </p>

    @if($schemaAudit === null)
        <p class="oc-muted">Click "Run Database Audit" above to check.</p>
    @else
        @php $findings = count($schemaAudit['unexpected']) + count($schemaAudit['expected_missing']); @endphp

        @if($findings === 0)
            <div class="oc-note ok">Clean — the live database exactly matches the migration history.</div>
        @else
            @if($schemaAudit['unexpected'] !== [])
                <div class="oc-note warn" style="margin-bottom:.75rem;">
                    <div class="oc-h" style="color: var(--warning-500, #f59e0b);margin-bottom:.35rem;">{{ count($schemaAudit['unexpected']) }} table(s) not accounted for by any migration</div>
                    <ul style="list-style:disc;padding-left:1.1rem;margin:0;" class="oc-mono">
                        @foreach($schemaAudit['unexpected'] as $table)<li>{{ $table }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @if($schemaAudit['expected_missing'] !== [])
                <div class="oc-note down" style="margin-bottom:.75rem;">
                    <div class="oc-h" style="color: var(--danger-500, #dc2626);margin-bottom:.35rem;">{{ count($schemaAudit['expected_missing']) }} table(s) a migration created but that no longer exist</div>
                    <ul style="list-style:disc;padding-left:1.1rem;margin:0;" class="oc-mono">
                        @foreach($schemaAudit['expected_missing'] as $table)<li>{{ $table }}</li>@endforeach
                    </ul>
                </div>
            @endif
        @endif

        @if($schemaAuditedAt)
            <p class="oc-muted">Last audited {{ \Illuminate\Support\Carbon::parse($schemaAuditedAt)->diffForHumans() }}</p>
        @endif
    @endif
</div>

{{-- Scheduled maintenance tasks already run on their own schedule; point at the page that lists them. --}}
<div class="oc-card">
    <div class="oc-card-head"><div class="oc-h">Scheduled maintenance tasks</div></div>
    <p class="oc-sub">
        Expired OTPs, abandoned carts, cached invoices, long-resolved refund images and abandoned backup/update runs are cleaned automatically on their own
        schedule. To run any of them on demand instead of waiting:
    </p>
    <a href="{{ \App\Filament\Pages\System\ScheduledTasksPage::getUrl() }}" class="oc-btn oc-btn-primary">
        <x-heroicon-o-clock style="width:.9rem;height:.9rem;" /> Open Scheduled Tasks
    </a>
</div>
