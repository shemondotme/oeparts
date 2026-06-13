<div class="space-y-4">
    {{-- Action Summary --}}
    <div class="p-4 rounded-xl" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
        <div class="text-xs font-bold uppercase tracking-widest font-mono mb-2" style="color: var(--color-text-muted);">Action Details</div>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="font-mono" style="color: var(--color-text-muted);">Type:</span>
                <span class="font-medium" style="color: var(--color-text-primary);">{{ ucfirst(str_replace('_', ' ', $record->action_type ?? 'N/A')) }}</span>
            </div>
            <div>
                <span class="font-mono" style="color: var(--color-text-muted);">Entity:</span>
                <span class="font-medium" style="color: var(--color-text-primary);">{{ class_basename($record->entity_type ?? 'N/A') }}</span>
            </div>
            <div>
                <span class="font-mono" style="color: var(--color-text-muted);">Affected Rows:</span>
                <span class="font-medium" style="color: var(--color-text-primary);">{{ number_format($record->affected_rows_count ?? 0) }}</span>
            </div>
            <div>
                <span class="font-mono" style="color: var(--color-text-muted);">Admin:</span>
                <span class="font-medium" style="color: var(--color-text-primary);">{{ $record->admin?->name ?? 'System' }}</span>
            </div>
            <div>
                <span class="font-mono" style="color: var(--color-text-muted);">IP Address:</span>
                <span class="font-mono text-xs" style="color: var(--color-text-primary);">{{ $record->ip_address ?? '—' }}</span>
            </div>
            <div>
                <span class="font-mono" style="color: var(--color-text-muted);">Timestamp:</span>
                <span class="font-mono text-xs" style="color: var(--color-text-primary);">{{ $record->created_at?->format('M j, Y H:i:s') ?? '—' }}</span>
            </div>
        </div>
    </div>

    {{-- Filters Applied --}}
    @if($record->filters)
        <div class="p-4 rounded-xl" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
            <div class="text-xs font-bold uppercase tracking-widest font-mono mb-2" style="color: var(--color-text-muted);">Filters Applied</div>
            <div class="font-mono text-xs p-3 rounded-lg" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle); color: var(--color-text-secondary);">
                @if(is_array($record->filters))
                    @foreach($record->filters as $key => $value)
                        <div><span style="color: var(--color-text-muted);">{{ $key }}:</span> {{ is_array($value) ? json_encode($value) : $value }}</div>
                    @endforeach
                @else
                    {{ $record->filters }}
                @endif
            </div>
        </div>
    @endif

    {{-- Updates Applied --}}
    @if($record->updates)
        <div class="p-4 rounded-xl" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
            <div class="text-xs font-bold uppercase tracking-widest font-mono mb-2" style="color: var(--color-text-muted);">Updates Applied</div>
            <div class="font-mono text-xs p-3 rounded-lg" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle); color: var(--color-text-secondary);">
                @if(is_array($record->updates))
                    @foreach($record->updates as $key => $value)
                        <div><span style="color: var(--color-text-muted);">{{ $key }}:</span> {{ is_array($value) ? json_encode($value) : $value }}</div>
                    @endforeach
                @else
                    {{ $record->updates }}
                @endif
            </div>
        </div>
    @endif

    {{-- Raw Payload --}}
    @if($record->payload)
        <div class="p-4 rounded-xl" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
            <div class="text-xs font-bold uppercase tracking-widest font-mono mb-2" style="color: var(--color-text-muted);">Raw Payload</div>
            <pre class="font-mono text-xs p-3 rounded-lg overflow-x-auto max-h-48" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle); color: var(--color-text-secondary);">{{ is_array($record->payload) ? json_encode($record->payload, JSON_PRETTY_PRINT) : $record->payload }}</pre>
        </div>
    @endif
</div>
