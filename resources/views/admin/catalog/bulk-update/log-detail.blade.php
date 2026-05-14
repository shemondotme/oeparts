@extends('layouts.admin')

@section('title', 'Bulk Update Log')
@section('page_title', 'Bulk Update Log')

@php
    $actionLabel = 'Generic ' . ucwords(str_replace('_', ' ', $log->entity_type ?? 'records')) . ' Update';
    if ($log->action_type) {
        $actionValue = $log->action_type instanceof \BackedEnum ? $log->action_type->value : $log->action_type;
        $actionLabel = ucwords(str_replace('_', ' ', $actionValue));
    }
@endphp

@section('header_actions')
    <a href="{{ route('admin.catalog.bulk-update.logs') }}" class="bp-btn-outline">
        <x-heroicon-o-arrow-left class="w-4 h-4" />
        Back to Logs
    </a>
@endsection

@section('content')
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <section class="bp-card lg:col-span-2">
        <header class="bp-card-header">
            <p class="bp-spec text-amber-ink">§ Audit · Bulk Operation</p>
            <h2 class="mt-1 font-display text-xl font-bold tracking-[-0.02em] text-ink">
                {{ $actionLabel }}<span class="text-amber">.</span>
            </h2>
        </header>

        <div class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2">
            <div class="border border-rule bg-ivory-alt p-4">
                <p class="bp-spec">Affected Records</p>
                <p class="mt-2 font-mono text-2xl font-bold text-ink">{{ number_format($log->affected_rows_count) }} records</p>
            </div>
            <div class="border border-rule bg-ivory-alt p-4">
                <p class="bp-spec">Entity Type</p>
                <p class="mt-2 font-mono text-lg text-ink">{{ $log->entity_type ?? 'products' }}</p>
            </div>
            <div class="border border-rule bg-ivory-alt p-4">
                <p class="bp-spec">Admin</p>
                <p class="mt-2 text-sm font-medium text-ink">{{ $log->admin->name ?? 'System' }}</p>
                <p class="mt-1 font-mono text-xs text-ink-muted">{{ $log->admin->email ?? 'No admin email' }}</p>
            </div>
            <div class="border border-rule bg-ivory-alt p-4">
                <p class="bp-spec">Created</p>
                <p class="mt-2 font-mono text-sm text-ink">{{ optional($log->created_at)->format('Y-m-d H:i:s') ?? 'Unknown' }}</p>
            </div>
        </div>
    </section>

    <aside class="bp-card">
        <header class="bp-card-header">
            <p class="bp-spec text-amber-ink">§ Request · Context</p>
        </header>
        <dl class="space-y-4 p-5">
            <div>
                <dt class="bp-spec">IP Address</dt>
                <dd class="mt-1 font-mono text-sm text-ink">{{ $log->ip_address ?? 'N/A' }}</dd>
            </div>
            <div>
                <dt class="bp-spec">User Agent</dt>
                <dd class="mt-1 break-words text-sm text-ink-muted">{{ $log->user_agent ?? 'N/A' }}</dd>
            </div>
        </dl>
    </aside>

    <section class="bp-card lg:col-span-3">
        <header class="bp-card-header">
            <p class="bp-spec text-amber-ink">§ Filters · Selection Criteria</p>
        </header>
        <pre class="overflow-x-auto p-5 font-mono text-xs text-ink">{{ json_encode($log->filters ?? $log->payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </section>

    <section class="bp-card lg:col-span-3">
        <header class="bp-card-header">
            <p class="bp-spec text-amber-ink">§ Updates · Applied Values</p>
        </header>
        <pre class="overflow-x-auto p-5 font-mono text-xs text-ink">{{ json_encode($log->updates ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </section>
</div>
@endsection
