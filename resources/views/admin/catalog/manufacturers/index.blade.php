@extends('layouts.admin')

@section('title', 'Manufacturers')
@section('page_title', 'Manufacturer Management')

@section('header_actions')
    <a href="{{ route('admin.catalog.manufacturers.create') }}" class="bp-btn-primary">
        <x-heroicon-o-plus class="w-4 h-4" />
        Add Manufacturer
    </a>
@endsection

@section('content')
<div class="space-y-6">

    {{-- Filters --}}
    <section class="bp-card">
        <header class="bp-card-header">
            <p class="bp-spec text-ink-muted">§ Filter · Manufacturers</p>
        </header>
        <form method="GET" action="{{ route('admin.catalog.manufacturers.index') }}"
              class="p-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label for="name" class="block bp-spec mb-2">§ Name</label>
                <input type="text" id="name" name="name" value="{{ request('name') }}"
                       placeholder="e.g. Bosch, Valeo, Continental"
                       class="bp-input">
            </div>
            <div>
                <label for="country_code" class="block bp-spec mb-2">§ Country</label>
                <select id="country_code" name="country_code" class="bp-select">
                    <option value="">All Countries</option>
                    @foreach($countries as $code => $name)
                        <option value="{{ $code }}" {{ request('country_code') == $code ? 'selected' : '' }}>
                            {{ $name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="active_status" class="block bp-spec mb-2">§ Status</label>
                <select id="active_status" name="active_status" class="bp-select">
                    <option value="">All</option>
                    <option value="active" {{ request('active_status') == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('active_status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div>
                <label for="oem_verified" class="block bp-spec mb-2">§ OEM Verified</label>
                <select id="oem_verified" name="oem_verified" class="bp-select">
                    <option value="">All</option>
                    <option value="verified" {{ request('oem_verified') == 'verified' ? 'selected' : '' }}>Verified</option>
                    <option value="not_verified" {{ request('oem_verified') == 'not_verified' ? 'selected' : '' }}>Not Verified</option>
                </select>
            </div>
            <div class="md:col-span-2 xl:col-span-4 flex items-center justify-end gap-4 pt-2 border-t border-rule">
                <a href="{{ route('admin.catalog.manufacturers.index') }}" class="bp-btn-ghost">Reset</a>
                <button type="submit" class="bp-btn-primary">Apply</button>
            </div>
        </form>
    </section>

    {{-- Table --}}
    <section class="bp-card overflow-hidden">
        <header class="bp-card-header flex items-center justify-between gap-4">
            <div>
                <p class="bp-spec text-amber-ink">§ Catalog · Manufacturers</p>
                <h2 class="mt-1 font-display text-xl font-bold text-ink tracking-[-0.02em]">
                    Manufacturer Registry<span class="text-amber">.</span>
                </h2>
            </div>
            <p class="font-mono text-xs text-ink-muted tabular-nums">
                {{ number_format($manufacturers->total()) }} records
            </p>
        </header>

        <div class="overflow-x-auto">
            <table class="bp-table">
                <thead>
                    <tr>
                        <th class="w-12">Logo</th>
                        <th>Name</th>
                        <th>Country</th>
                        <th>OEM</th>
                        <th>Status</th>
                        <th>Products</th>
                        <th class="text-right pr-5">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($manufacturers as $manufacturer)
                        <tr class="cursor-pointer" data-edit-url="{{ route('admin.catalog.manufacturers.edit', $manufacturer) }}">
                            <td>
                                @if($manufacturer->logo)
                                    <img src="{{ $manufacturer->logo->file_url }}"
                                         alt="{{ trans_field($manufacturer->name) }}"
                                         class="h-8 w-8 object-contain border border-rule bg-paper p-0.5">
                                @else
                                    <div class="h-8 w-8 bg-ivory-alt border border-rule flex items-center justify-center">
                                        <x-heroicon-o-photo class="w-4 h-4 text-ink-muted" />
                                    </div>
                                @endif
                            </td>
                            <td>
                                <p class="text-sm font-bold text-ink">{{ trans_field($manufacturer->name) }}</p>
                                @if($manufacturer->slug)
                                    <p class="font-mono text-xs text-ink-muted mt-0.5">{{ $manufacturer->slug }}</p>
                                @endif
                            </td>
                            <td>
                                <p class="text-sm text-ink">{{ $countries[$manufacturer->country_code] ?? $manufacturer->country_code ?? '—' }}</p>
                            </td>
                            <td>
                                @if($manufacturer->is_verified_oem)
                                    <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border-green-600/30 bg-green-50 text-green-700">
                                        Verified
                                    </span>
                                @else
                                    <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border-rule bg-ivory-alt text-ink-muted">
                                        Not Verified
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($manufacturer->is_active)
                                    <span class="font-mono text-xs text-emerald-600 font-bold">ACTIVE</span>
                                @else
                                    <span class="font-mono text-xs text-ink-muted font-bold">INACTIVE</span>
                                @endif
                            </td>
                            <td>
                                <p class="font-mono text-sm tabular-nums text-ink">
                                    {{ number_format($manufacturer->products_count ?? $manufacturer->products()->count()) }}
                                </p>
                            </td>
                            <td class="text-right pr-5 no-row-click">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('admin.catalog.manufacturers.edit', $manufacturer) }}"
                                       class="bp-btn-ghost gap-1 text-[10px]">
                                        <x-heroicon-o-pencil-square class="w-3.5 h-3.5" />
                                        Edit
                                    </a>
                                    <form action="{{ route('admin.catalog.manufacturers.destroy', $manufacturer) }}" method="POST"
                                          onsubmit="return confirm('Delete this manufacturer?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="bp-btn-ghost text-red-600 hover:text-red-700 gap-1 text-[10px]">
                                            <x-heroicon-o-trash class="w-3.5 h-3.5" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-16 text-center">
                                <x-heroicon-o-inbox class="w-10 h-10 mx-auto text-ink/20 mb-3" />
                                <p class="font-display font-bold text-ink">No manufacturers found</p>
                                <p class="mt-1 text-sm text-ink-muted">Try adjusting your filters or add one.</p>
                                <a href="{{ route('admin.catalog.manufacturers.create') }}" class="bp-btn-primary mt-5 inline-flex">
                                    <x-heroicon-o-plus class="w-4 h-4" />
                                    Add First Manufacturer
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($manufacturers->hasPages())
            <div class="px-5 py-4 border-t border-rule">
                {{ $manufacturers->withQueryString()->links() }}
            </div>
        @endif
    </section>

</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('tr[data-edit-url]').forEach(row => {
            row.addEventListener('click', function (e) {
                if (e.target.closest('a, button, form, .no-row-click')) return;
                window.location.href = this.dataset.editUrl;
            });
        });
    });
</script>
@endpush
