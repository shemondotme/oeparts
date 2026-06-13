<div class="space-y-4">
    {{-- Revision Meta --}}
    <div class="p-4 rounded-xl" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
        <div class="text-xs font-bold uppercase tracking-widest font-mono mb-2" style="color: var(--color-text-muted);">Revision Details</div>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="font-mono" style="color: var(--color-text-muted);">Content Type:</span>
                <span class="font-medium" style="color: var(--color-text-primary);">{{ class_basename($record->revisionable_type ?? 'N/A') }}</span>
            </div>
            <div>
                <span class="font-mono" style="color: var(--color-text-muted);">Record ID:</span>
                <span class="font-mono text-xs" style="color: var(--color-text-primary);">{{ $record->revisionable_id ?? '—' }}</span>
            </div>
            <div>
                <span class="font-mono" style="color: var(--color-text-muted);">Admin:</span>
                <span class="font-medium" style="color: var(--color-text-primary);">{{ $record->admin?->name ?? 'System' }}</span>
            </div>
            <div>
                <span class="font-mono" style="color: var(--color-text-muted);">Timestamp:</span>
                <span class="font-mono text-xs" style="color: var(--color-text-primary);">{{ $record->created_at?->format('M j, Y H:i:s') ?? '—' }}</span>
            </div>
        </div>
    </div>

    {{-- Content Snapshot --}}
    @if($record->content_snapshot && is_array($record->content_snapshot))
        <div class="p-4 rounded-xl" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
            <div class="text-xs font-bold uppercase tracking-widest font-mono mb-2" style="color: var(--color-text-muted);">Content Snapshot</div>
            <div class="space-y-2">
                @foreach($record->content_snapshot as $key => $value)
                    <div class="p-3 rounded-lg" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
                        <div class="text-xs font-bold font-mono mb-1" style="color: var(--color-text-muted);">{{ $key }}</div>
                        <div class="text-sm" style="color: var(--color-text-primary);">
                            @if(is_array($value))
                                <pre class="font-mono text-xs p-2 rounded overflow-x-auto max-h-32" style="background: var(--color-bg-inset);">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            @elseif(is_bool($value))
                                {{ $value ? 'true' : 'false' }}
                            @elseif(is_null($value))
                                <span style="color: var(--color-text-muted);">null</span>
                            @else
                                {{ $value }}
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="p-4 rounded-xl text-center" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
            <p class="text-sm" style="color: var(--color-text-muted);">No content snapshot recorded for this revision.</p>
        </div>
    @endif
</div>
