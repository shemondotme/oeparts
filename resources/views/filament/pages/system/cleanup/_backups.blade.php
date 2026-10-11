@use('App\Services\Cleanup\CleanupText', 'T')
{{-- Backups & updates: what retention would keep/delete (exactly what the real run does), plus leftovers. --}}
@php

    $plan = $this->backupPlan;
    $rows = $this->targetsIn('backups');
    $prune = $plan['prune'] ?? collect();
    $keepCount = $plan ? count($plan['keep']) : 0;
    $pruneBytes = (int) $prune->sum(fn ($r) => (int) $r->total_bytes);
    $policy = [
        'daily' => (int) settings('backup.retention_daily', config('backup.retention.daily', 7)),
        'weekly' => (int) settings('backup.retention_weekly', config('backup.retention.weekly', 4)),
        'monthly' => (int) settings('backup.retention_monthly', config('backup.retention.monthly', 6)),
    ];
@endphp
<div class="oc-card">
    <div class="oc-card-head">
        <div class="oc-h">Backups &amp; updates</div>
        <a class="oc-btn" href="{{ \App\Filament\Pages\System\BackupDashboard::getUrl() }}">
            <x-heroicon-o-archive-box style="width:.9rem;height:.9rem;" /> Manage backups
        </a>
    </div>
    <p class="oc-sub">
        Backups are kept by a daily / weekly / monthly policy ({{ $policy['daily'] }} / {{ $policy['weekly'] }} / {{ $policy['monthly'] }}, set in Backup settings).
        The newest backup of each kind, and any backup an update could still roll back to, are always kept — whatever the numbers say.
    </p>

    @if($plan === null)
        <div class="oc-note warn">The retention plan could not be calculated right now (see the cleanup log).</div>
    @else
        <div class="oc-grid" style="margin-bottom:1rem;">
            <div class="oc-tile"><div class="oc-tile-l">Kept</div><div class="oc-tile-v">{{ $keepCount }}</div><div class="oc-tile-m">backup(s) with files on disk</div></div>
            <div class="oc-tile"><div class="oc-tile-l">Over the policy</div><div class="oc-tile-v">{{ $prune->count() }}</div><div class="oc-tile-m">{{ $prune->count() > 0 ? '~'.T::bytes($pruneBytes).' would be freed' : 'nothing to remove' }}</div></div>
        </div>

        @php
            $all = \App\Models\BackupRun::query()->successful()->whereNull('meta->pruned_at')->orderByRaw('COALESCE(finished_at, started_at, created_at) DESC')->limit(12)->get();
        @endphp
        @if($all->isNotEmpty())
            <div style="overflow-x:auto;">
                <table class="oc-table">
                    <thead><tr><th>#</th><th>Kind</th><th>Taken</th><th class="num">Size</th><th>Decision</th></tr></thead>
                    <tbody>
                    @foreach($all as $b)
                        @php $why = $plan['keep'][$b->id] ?? null; @endphp
                        <tr>
                            <td class="oc-mono">{{ $b->id }}</td>
                            <td>{{ $b->profile }}</td>
                            <td>{{ ($b->finished_at ?? $b->created_at)->diffForHumans() }}</td>
                            <td class="num">{{ T::bytes((int) $b->total_bytes) }}</td>
                            <td>
                                @if($why !== null)
                                    <span class="oc-pill ok">keep</span>
                                    <span class="oc-muted">{{ implode(', ', array_map(fn ($w) => match ($w) {
                                        'gfs' => 'within the policy',
                                        'newest_of_profile' => 'newest of its kind',
                                        'update_referenced' => 'an update may roll back to it',
                                        'incremental_chain' => 'later backups depend on it',
                                        default => $w,
                                    }, $why)) }}</span>
                                @else
                                    <span class="oc-pill warn">over the policy</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="oc-muted" style="margin-top:.4rem;">Showing the 12 most recent. Delete a specific backup from Manage backups; the buttons below apply the policy.</p>
        @endif
    @endif

    <div style="margin-top:1rem;">
        @foreach($rows as $row)
            @include('filament.pages.system.cleanup._row', ['row' => $row])
        @endforeach
    </div>
</div>
