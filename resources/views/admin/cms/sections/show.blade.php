@extends('layouts.admin')

@section('title', 'Section Details')
@section('page_title', 'Section Details')

@section('breadcrumbs')
    <a href="{{ route('admin.cms.sections.index') }}" class="hover:text-amber-ink">Sections</a>
    <span>/</span>
    <span>{{ trans_field($section->title) ?: $section->type }}</span>
@endsection

@section('header_actions')
    <a href="{{ route('admin.cms.sections.edit', $section) }}" class="bp-btn-primary">
        <x-heroicon-o-pencil-square class="w-4 h-4" />
        Edit Section
    </a>
@endsection

@section('content')
@php
    $statusClass = match($section->status->value) {
        'published' => 'text-emerald-600',
        'scheduled' => 'text-blue-600',
        'archived' => 'text-ink-muted',
        default => 'text-amber-ink',
    };
@endphp

<div class="space-y-6">
    <section class="bp-card">
        <header class="bp-card-header flex items-center justify-between gap-4">
            <div>
                <p class="bp-spec text-amber-ink">§ CMS · Section Manifest</p>
                <h2 class="mt-1 font-display text-2xl font-bold text-ink tracking-[-0.02em]">
                    {{ trans_field($section->title) ?: $section->type }}<span class="text-amber">.</span>
                </h2>
            </div>
            <span class="font-mono text-xs uppercase tracking-[0.18em] {{ $statusClass }}">
                {{ $section->status->label() }}
            </span>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-0">
            <div class="lg:col-span-2 p-5 border-b lg:border-b-0 lg:border-r border-rule">
                <h3 class="bp-spec mb-3">§ Content Snapshot</h3>
                <div class="space-y-4">
                    @foreach(($section->title ?? []) as $lang => $title)
                        <div class="border border-rule bg-ivory-alt p-4">
                            <p class="font-mono text-xs uppercase tracking-[0.18em] text-ink-muted">{{ strtoupper($lang) }}</p>
                            <p class="mt-2 font-display text-lg font-bold text-ink">{{ $title ?: 'Untitled' }}</p>
                            <pre class="mt-3 max-h-72 overflow-auto border border-rule bg-paper p-3 text-xs text-ink">{{ json_encode($section->content[$lang] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    @endforeach
                </div>
            </div>

            <aside class="p-5 space-y-5">
                <div>
                    <h3 class="bp-spec mb-3">§ Registry</h3>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-ink-muted">Type</dt>
                            <dd class="font-mono text-ink">{{ $section->type }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-ink-muted">Location</dt>
                            <dd class="font-mono text-ink">{{ $section->location->value }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-ink-muted">Active</dt>
                            <dd class="font-mono {{ $section->is_active ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ $section->is_active ? 'YES' : 'NO' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-ink-muted">Sort</dt>
                            <dd class="font-mono text-ink">{{ $section->sort_order }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-ink-muted">Publish At</dt>
                            <dd class="font-mono text-ink">{{ optional($section->publish_at)->format('Y-m-d H:i') ?: 'Immediate' }}</dd>
                        </div>
                    </dl>
                </div>
            </aside>
        </div>
    </section>

    <section class="bp-card">
        <header class="bp-card-header">
            <p class="bp-spec text-amber-ink">§ Audit · Version History</p>
            <h2 class="mt-1 font-display text-xl font-bold text-ink">Version History<span class="text-amber">.</span></h2>
        </header>
        <div class="overflow-x-auto">
            <table class="bp-table">
                <thead>
                    <tr>
                        <th>Version</th>
                        <th>Action</th>
                        <th>Summary</th>
                        <th>Created By</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($versions as $version)
                        <tr>
                            <td class="font-mono">#{{ $version->id }}</td>
                            <td class="font-mono uppercase">{{ $version->action }}</td>
                            <td>{{ $version->change_summary ?: 'No summary recorded' }}</td>
                            <td>{{ $version->author->name ?? 'System' }}</td>
                            <td class="font-mono text-xs text-ink-muted">{{ $version->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-ink-muted">No versions recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
