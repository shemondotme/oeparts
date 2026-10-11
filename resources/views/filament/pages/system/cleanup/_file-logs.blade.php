@use('App\Services\Cleanup\CleanupText', 'T')
{{-- storage/logs: what is in there, delete or empty chosen files, plus the age-based rules for them. --}}
@php

    $files = $this->logFiles;
    $totalBytes = array_sum(array_column($files, 'bytes'));
    $logRows = array_intersect_key($this->targets, array_flip(['file_logs', 'update_logs', 'oversized_logs']));
    $isSuper = auth('admin')->user()?->hasRole('super_admin');
@endphp
<div class="oc-card">
    <div class="oc-card-head">
        <div class="oc-h">Log files</div>
        @if($isSuper)
            <a class="oc-btn" href="{{ \App\Filament\Pages\System\LogViewerPage::getUrl() }}">
                <x-heroicon-o-document-text style="width:.9rem;height:.9rem;" /> Open Log Viewer
            </a>
        @endif
    </div>
    <p class="oc-sub">
        The application's log files ({{ count($files) }} file(s), {{ T::bytes($totalBytes) }}). Tick files to delete them, or to empty them in place:
        a file written to in the last hour is "active" and can only be emptied (the application keeps logging into it).
    </p>

    @if($files === [])
        <p class="oc-muted">No log files.</p>
    @else
        <div style="overflow-x:auto;max-height:22rem;overflow-y:auto;">
            <table class="oc-table">
                <thead>
                    <tr><th style="width:2rem;"></th><th>File</th><th class="num">Size</th><th>Last written</th><th>State</th></tr>
                </thead>
                <tbody>
                @foreach($files as $file)
                    <tr wire:key="oc-log-{{ $file['name'] }}">
                        <td><input type="checkbox" value="{{ $file['name'] }}" wire:model.live="selectedLogs" aria-label="Select {{ $file['name'] }}"></td>
                        <td class="oc-mono">{{ $file['name'] }}</td>
                        <td class="num">{{ T::bytes($file['bytes']) }}</td>
                        <td>{{ \Illuminate\Support\Carbon::createFromTimestamp($file['modified'])->diffForHumans() }}</td>
                        <td>
                            @if($file['active'])<span class="oc-pill info">active</span>@endif
                            @if($file['oversized'])<span class="oc-pill warn">over 50 MB</span>@endif
                            @if(! $file['active'] && ! $file['oversized'])<span class="oc-pill muted">idle</span>@endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.75rem;align-items:center;">
            <button type="button" class="oc-btn oc-btn-danger" wire:click="mountAction('deleteLogs')" @disabled(count($this->selectedLogs) === 0)>
                <x-heroicon-o-trash style="width:.9rem;height:.9rem;" /> Delete selected
            </button>
            <button type="button" class="oc-btn oc-btn-warn" wire:click="mountAction('emptyLogs')" @disabled(count($this->selectedLogs) === 0)>
                Empty selected
            </button>
            <span class="oc-muted">{{ count($this->selectedLogs) }} selected</span>
        </div>
    @endif

    @if($logRows !== [])
        <div style="margin-top:1.25rem;">
            <div class="oc-tile-l" style="margin-bottom:.25rem;">Automatic rules for log files</div>
            @foreach($logRows as $row)
                @include('filament.pages.system.cleanup._row', ['row' => $row])
            @endforeach
        </div>
    @endif
</div>
