@php
    $oldValues = is_array($record->old_values) ? $record->old_values : [];
    $newValues = is_array($record->new_values) ? $record->new_values : [];
    $allKeys   = collect(array_unique(array_merge(array_keys($oldValues), array_keys($newValues))));

    $modelShort = $record->model_type ? class_basename($record->model_type) : null;

    $ts = $record->created_at
        ? (\Carbon\Carbon::parse($record->created_at)->format('d M Y, H:i:s'))
        : '—';
@endphp

<div class="space-y-4 text-sm">

    {{-- Context strip --}}
    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 rounded-lg border border-[var(--border-primary)] bg-[var(--surface-base)] p-4">
        <div>
            <dt class="op-widget-title mb-0.5">Admin</dt>
            <dd class="font-medium text-[var(--text-primary)]">{{ $record->admin?->name ?? 'System' }}</dd>
        </div>
        <div>
            <dt class="op-widget-title mb-0.5">Action</dt>
            <dd>
                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset
                    bg-[var(--surface-card)] text-[var(--text-primary)] ring-[var(--border-primary)]">
                    {{ $record->action }}
                </span>
            </dd>
        </div>
        @if ($modelShort)
        <div>
            <dt class="op-widget-title mb-0.5">Model</dt>
            <dd class="font-mono text-[var(--text-primary)]">{{ $modelShort }}
                @if ($record->model_id)
                    <span class="text-[var(--text-secondary)]">#{{ $record->model_id }}</span>
                @endif
            </dd>
        </div>
        @endif
        <div>
            <dt class="op-widget-title mb-0.5">IP Address</dt>
            <dd class="font-mono text-[var(--text-primary)]">{{ $record->ip_address ?? '—' }}</dd>
        </div>
        <div class="col-span-2">
            <dt class="op-widget-title mb-0.5">Timestamp</dt>
            <dd class="font-mono text-[var(--text-secondary)]">{{ $ts }}</dd>
        </div>
    </dl>

    {{-- Diff table --}}
    @if ($allKeys->isNotEmpty())
    <div>
        <h4 class="op-widget-title mb-2">Field Changes</h4>
        <div class="overflow-hidden rounded-lg border border-[var(--border-primary)]">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-[var(--border-primary)] bg-[var(--surface-base)]">
                        <th class="op-table-header px-3 py-2 text-left w-1/4">Field</th>
                        <th class="op-table-header px-3 py-2 text-left w-5/12">Before</th>
                        <th class="op-table-header px-3 py-2 text-left w-5/12">After</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border-primary)]">
                    @foreach ($allKeys as $key)
                        @php
                            $hasOld = array_key_exists($key, $oldValues);
                            $hasNew = array_key_exists($key, $newValues);
                            $oldVal = $hasOld ? (is_array($oldValues[$key]) ? json_encode($oldValues[$key]) : $oldValues[$key]) : null;
                            $newVal = $hasNew ? (is_array($newValues[$key]) ? json_encode($newValues[$key]) : $newValues[$key]) : null;

                            $rowClass = match(true) {
                                $hasOld && $hasNew && (string)$oldVal !== (string)$newVal
                                    => 'bg-amber-50 dark:bg-amber-950/20',
                                $hasNew && !$hasOld
                                    => 'bg-green-50 dark:bg-green-950/20',
                                $hasOld && !$hasNew
                                    => 'bg-red-50 dark:bg-red-950/20',
                                default => '',
                            };
                            $indicator = match(true) {
                                $hasNew && !$hasOld   => '+',
                                $hasOld && !$hasNew   => '−',
                                (string)$oldVal !== (string)$newVal => '~',
                                default               => '',
                            };
                            $indicatorColor = match($indicator) {
                                '+'     => 'text-green-600 dark:text-green-400',
                                '−'     => 'text-red-600 dark:text-red-400',
                                '~'     => 'text-amber-600 dark:text-amber-400',
                                default => 'text-[var(--text-secondary)]',
                            };
                        @endphp
                        <tr class="{{ $rowClass }}">
                            <td class="px-3 py-2 font-mono text-[var(--text-secondary)]">
                                <span class="font-bold {{ $indicatorColor }} mr-1">{{ $indicator }}</span>{{ $key }}
                            </td>
                            <td class="px-3 py-2 font-mono break-all
                                {{ (!$hasOld) ? 'text-[var(--text-secondary)] italic' : 'text-[var(--text-primary)]' }}">
                                {{ $hasOld ? (strlen((string)$oldVal) > 120 ? substr((string)$oldVal, 0, 120).'…' : $oldVal) : 'null' }}
                            </td>
                            <td class="px-3 py-2 font-mono break-all
                                {{ (!$hasNew) ? 'text-[var(--text-secondary)] italic' : 'text-[var(--text-primary)]' }}">
                                {{ $hasNew ? (strlen((string)$newVal) > 120 ? substr((string)$newVal, 0, 120).'…' : $newVal) : 'null' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @else
    <p class="op-empty-text text-center py-4">No field-level data recorded for this action.</p>
    @endif

</div>
