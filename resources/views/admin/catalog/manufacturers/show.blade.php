@extends('layouts.admin')

@section('title', 'Manufacturer — ' . trans_field($manufacturer->name))
@section('page_title', 'Manufacturer Details')

@section('header_actions')
    <a href="{{ route('admin.catalog.manufacturers.edit', $manufacturer) }}" class="bp-btn-outline gap-1">
        <x-heroicon-o-pencil class="w-4 h-4" />
        Edit
    </a>
    <a href="{{ route('admin.catalog.manufacturers.index') }}" class="bp-btn-ghost gap-1">
        <x-heroicon-o-arrow-left class="w-4 h-4" />
        Back
    </a>
@endsection

@section('content')
<div class="space-y-6">

    {{-- Header strip --}}
    <div class="bp-card p-5 flex flex-wrap items-center gap-4">
        @if($manufacturer->logo)
            <img src="{{ $manufacturer->logo->file_url }}" alt="{{ trans_field($manufacturer->name) }}"
                 class="h-14 w-14 object-contain border border-rule bg-paper p-1">
        @else
            <div class="h-14 w-14 bg-ivory-alt border border-rule flex items-center justify-center">
                <x-heroicon-o-photo class="w-6 h-6 text-ink-muted" />
            </div>
        @endif
        <div>
            <p class="bp-spec text-ink-muted">§ Manufacturer</p>
            <h2 class="font-display text-2xl font-bold text-ink tracking-[-0.02em]">
                {{ trans_field($manufacturer->name) }}<span class="text-amber">.</span>
            </h2>
            @if($manufacturer->slug)
                <p class="font-mono text-xs text-ink-muted mt-0.5">{{ $manufacturer->slug }}</p>
            @endif
        </div>
        <div class="ml-auto flex items-center gap-2">
            @if($manufacturer->is_active)
                <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border-green-600/30 bg-green-50 text-green-700">Active</span>
            @else
                <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border-rule bg-ivory-alt text-ink-muted">Inactive</span>
            @endif
            @if($manufacturer->is_verified_oem ?? false)
                <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border-blue-600/30 bg-blue-50 text-blue-700">OEM Verified</span>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Left: Info --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Basic Information --}}
            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Basic · Information</p>
                </header>
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Name (JSON)</p>
                        <div class="bg-ivory-alt border border-rule p-3">
                            <pre class="font-mono text-xs text-ink whitespace-pre-wrap">{{ json_encode($manufacturer->name, JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    </div>
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Slug</p>
                        <p class="font-mono text-sm text-ink">{{ $manufacturer->slug ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Country</p>
                        <p class="text-sm text-ink">{{ $countries[$manufacturer->country_code] ?? $manufacturer->country_code ?? 'Not specified' }}</p>
                    </div>
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Logo</p>
                        @if($manufacturer->logo)
                            <a href="{{ route('admin.media.show', $manufacturer->logo) }}" class="font-mono text-xs text-amber-ink hover:underline">
                                {{ $manufacturer->logo->original_filename }}
                            </a>
                        @else
                            <p class="text-sm text-ink-muted">No logo</p>
                        @endif
                    </div>
                </div>
            </section>

            @if($manufacturer->description)
            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Description</p>
                </header>
                <div class="p-5">
                    <div class="bg-ivory-alt border border-rule p-3">
                        <pre class="font-mono text-xs text-ink whitespace-pre-wrap">{{ json_encode($manufacturer->description, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                </div>
            </section>
            @endif

            @if($manufacturer->website_url || $manufacturer->contact_email)
            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Contact · Website</p>
                </header>
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                    @if($manufacturer->website_url)
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Website</p>
                        <a href="{{ $manufacturer->website_url }}" target="_blank" class="text-sm text-amber-ink hover:underline break-all">
                            {{ $manufacturer->website_url }}
                        </a>
                    </div>
                    @endif
                    @if($manufacturer->contact_email)
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Contact Email</p>
                        <a href="mailto:{{ $manufacturer->contact_email }}" class="text-sm text-amber-ink hover:underline">
                            {{ $manufacturer->contact_email }}
                        </a>
                    </div>
                    @endif
                </div>
            </section>
            @endif

            {{-- Statistics --}}
            <div class="grid grid-cols-3 gap-4">
                <div class="bp-card p-5 text-center">
                    <p class="font-mono text-3xl font-bold tabular-nums text-ink">{{ $manufacturer->products->count() }}</p>
                    <p class="mt-1 text-xs text-ink-muted">Products</p>
                </div>
                <div class="bp-card p-5 text-center">
                    <p class="font-mono text-3xl font-bold tabular-nums text-ink">{{ $manufacturer->carModels->count() }}</p>
                    <p class="mt-1 text-xs text-ink-muted">Car Models</p>
                </div>
                <div class="bp-card p-5 text-center">
                    <p class="font-mono text-sm font-bold text-ink">{{ $manufacturer->created_at->diffForHumans() }}</p>
                    <p class="mt-1 text-xs text-ink-muted">Created</p>
                </div>
            </div>

        </div>

        {{-- Right: Actions + Meta --}}
        <div class="space-y-6">

            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Quick · Actions</p>
                </header>
                <div class="p-5 space-y-2">
                    <a href="{{ route('admin.catalog.manufacturers.edit', $manufacturer) }}"
                       class="bp-btn-outline w-full justify-center gap-1">
                        <x-heroicon-o-pencil class="w-4 h-4" />
                        Edit Manufacturer
                    </a>
                    <a href="{{ route('admin.catalog.products.index', ['manufacturer_id' => $manufacturer->id]) }}"
                       class="bp-btn-outline w-full justify-center gap-1">
                        <x-heroicon-o-cube class="w-4 h-4" />
                        View Products
                    </a>
                    <a href="{{ route('admin.catalog.car-models.index', ['manufacturer_id' => $manufacturer->id]) }}"
                       class="bp-btn-outline w-full justify-center gap-1">
                        <x-heroicon-o-truck class="w-4 h-4" />
                        View Car Models
                    </a>
                    <button type="button"
                            onclick="if(confirm('Delete this manufacturer? This cannot be undone.')) { document.getElementById('delete-form').submit(); }"
                            class="bp-btn-ghost text-red-600 hover:text-red-700 w-full justify-center gap-1">
                        <x-heroicon-o-trash class="w-4 h-4" />
                        Delete Manufacturer
                    </button>
                </div>
            </section>

            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Metadata</p>
                </header>
                <div class="p-5 space-y-3">
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Created</p>
                        <p class="font-mono text-xs tabular-nums text-ink">{{ $manufacturer->created_at->format('Y-m-d H:i') }}</p>
                    </div>
                    <div class="border-t border-rule pt-3">
                        <p class="bp-spec text-ink-muted mb-1">§ Updated</p>
                        <p class="font-mono text-xs tabular-nums text-ink">{{ $manufacturer->updated_at->format('Y-m-d H:i') }}</p>
                    </div>
                    @if($manufacturer->deleted_at)
                    <div class="border-t border-rule pt-3">
                        <p class="bp-spec text-red-600 mb-1">§ Deleted</p>
                        <p class="font-mono text-xs tabular-nums text-red-600">{{ $manufacturer->deleted_at->format('Y-m-d H:i') }}</p>
                    </div>
                    @endif
                </div>
            </section>

        </div>
    </div>

</div>

<form id="delete-form" action="{{ route('admin.catalog.manufacturers.destroy', $manufacturer) }}" method="POST" class="hidden">
    @csrf
    @method('DELETE')
</form>
@endsection
