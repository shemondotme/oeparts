@extends('layouts.admin')

@section('title', 'Bulk Update Logs')
@section('page_title', 'Bulk Update Logs')

@section('header_actions')
    <a href="{{ route('admin.catalog.bulk-update.index') }}" class="bp-btn-primary">
        <x-heroicon-o-bolt class="w-4 h-4" />
        New Bulk Update
    </a>
@endsection

@section('content')
@php
    $describeAction = function ($log): string {
        if ($log->action_type) {
            $value = $log->action_type instanceof \BackedEnum ? $log->action_type->value : $log->action_type;
            return ucwords(str_replace('_', ' ', $value));
        }

        return 'Generic ' . ucwords(str_replace('_', ' ', $log->entity_type ?? 'records')) . ' Update';
    };
@endphp

<section class="bp-card">
    <header class="bp-card-header flex items-center justify-between gap-4">
        <div>
            <p class="bp-spec text-amber-ink">§ Audit · Bulk Operations</p>
            <h2 class="mt-1 font-display text-xl font-bold text-ink tracking-[-0.02em]">
                Bulk Update History<span class="text-amber">.</span>
            </h2>
        </div>
        <span class="font-mono text-xs uppercase tracking-[0.18em] text-ink-muted">
            {{ number_format($logs->total()) }} logs
        </span>
    </header>

    <div class="overflow-x-auto">
        <table class="bp-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Admin</th>
                    <th>Action</th>
                    <th>Target</th>
                    <th>Affected</th>
                    <th class="text-right">Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td>
                            <div class="font-mono text-xs text-ink">{{ $log->created_at->format('Y-m-d') }}</div>
                            <div class="font-mono text-xs text-ink-muted">{{ $log->created_at->format('H:i:s') }}</div>
                        </td>
                        <td>
                            <div class="font-medium">{{ $log->admin->name ?? 'System' }}</div>
                            <div class="text-xs text-ink-muted">{{ $log->admin->email ?? '' }}</div>
                        </td>
                        <td>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-none bg-ivory-alt border border-rule font-mono text-xs uppercase tracking-wider text-ink">
                                {{ $describeAction($log) }}
                            </span>
                        </td>
                        <td>
                            @if($log->targetManufacturer)
                                <a href="{{ route('admin.catalog.manufacturers.show', $log->targetManufacturer) }}" class="hover:text-amber-ink hover:underline">
                                    {{ trans_field($log->targetManufacturer->name) }}
                                </a>
                            @else
                                <span class="font-mono text-xs">{{ $log->entity_type ?? 'products' }}</span>
                            @endif
                        </td>
                        <td class="font-mono">{{ number_format($log->affected_rows_count) }} records</td>
                        <td>
                            <div class="flex justify-end">
                                <a href="{{ route('admin.catalog.bulk-update.logs.show', $log) }}" class="bp-btn-ghost">
                                    <x-heroicon-o-eye class="w-4 h-4" />
                                    View
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center">
                            <x-heroicon-o-clock class="mx-auto h-10 w-10 text-ink-muted" />
                            <p class="mt-3 font-display text-lg font-bold text-ink">No bulk update logs found</p>
                            <p class="mt-1 text-sm text-ink-muted">Bulk operations will appear here after execution.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
        <div class="border-t border-rule bg-ivory-alt px-5 py-4">
            {{ $logs->withQueryString()->links() }}
        </div>
    @endif
</section>
@endsection
