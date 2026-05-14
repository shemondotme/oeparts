@extends('layouts.admin')

@section('title', 'Coupons')
@section('page_title', 'Coupon Management')

@section('header_actions')
    <a href="{{ route('admin.coupons.create') }}" class="bp-btn-primary gap-1">
        <x-heroicon-o-plus class="w-4 h-4" />
        New Coupon
    </a>
@endsection

@section('content')
<div class="space-y-6">

    {{-- Filters --}}
    <section class="bp-card">
        <header class="bp-card-header">
            <p class="bp-spec text-ink-muted">§ Filter · Coupons</p>
        </header>
        <form method="GET" action="{{ route('admin.coupons.index') }}"
              class="p-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label for="search" class="block bp-spec mb-2">§ Search</label>
                <input type="text" id="search" name="search" value="{{ request('search') }}"
                       placeholder="Code or name..."
                       class="bp-input">
            </div>
            <div>
                <label for="is_active" class="block bp-spec mb-2">§ Status</label>
                <select id="is_active" name="is_active" class="bp-select">
                    <option value="all">All</option>
                    <option value="active"   {{ request('is_active') === 'active'   ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('is_active') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="md:col-span-2 xl:col-span-2 flex items-center justify-end gap-4 pt-2 border-t border-rule md:border-0 md:pt-0 self-end">
                <a href="{{ route('admin.coupons.index') }}" class="bp-btn-ghost">Reset</a>
                <button type="submit" class="bp-btn-primary">Apply</button>
            </div>
        </form>
    </section>

    {{-- Flash --}}
    @if(session('success'))
    <div class="flex items-center gap-3 border border-green-600/30 bg-green-50 px-4 py-3">
        <x-heroicon-o-check-circle class="w-5 h-5 text-green-600 flex-shrink-0" />
        <p class="text-sm text-green-700">{{ session('success') }}</p>
    </div>
    @endif

    {{-- Table --}}
    <section class="bp-card overflow-hidden">
        <header class="bp-card-header flex items-center justify-between gap-4">
            <div>
                <p class="bp-spec text-amber-ink">§ Marketing · Coupons</p>
                <h2 class="mt-1 font-display text-xl font-bold text-ink tracking-[-0.02em]">
                    Discount Codes<span class="text-amber">.</span>
                </h2>
            </div>
            <p class="font-mono text-xs text-ink-muted tabular-nums">
                {{ number_format($coupons->total()) }} records
            </p>
        </header>

        <div class="overflow-x-auto">
            <table class="bp-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Discount</th>
                        <th>Usage</th>
                        <th>Expires</th>
                        <th>Status</th>
                        <th class="text-right pr-5">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($coupons as $coupon)
                        <tr class="cursor-pointer"
                            onclick="if(!event.target.closest('a,button,form')) window.location.href='{{ route('admin.coupons.show', $coupon) }}'">
                            <td>
                                <span class="font-mono text-sm font-bold text-amber-ink tracking-wider">{{ $coupon->code }}</span>
                            </td>
                            <td>
                                <p class="text-sm text-ink">{{ $coupon->name }}</p>
                            </td>
                            <td>
                                <p class="font-mono text-sm tabular-nums font-bold text-ink">
                                    @if($coupon->discount_type->value === 'percentage')
                                        {{ $coupon->discount_value }}%
                                    @else
                                        {{ format_money($coupon->discount_value) }}
                                    @endif
                                </p>
                            </td>
                            <td>
                                <p class="font-mono text-sm tabular-nums text-ink">
                                    {{ $coupon->usages_count ?? 0 }} / {{ $coupon->usage_limit ?? '∞' }}
                                </p>
                            </td>
                            <td>
                                <p class="font-mono text-xs tabular-nums text-ink">
                                    {{ $coupon->expires_at ? $coupon->expires_at->format('Y-m-d') : '—' }}
                                </p>
                            </td>
                            <td>
                                <button
                                    x-data="{ active: {{ $coupon->is_active ? 'true' : 'false' }} }"
                                    x-on:click.stop="fetch('{{ route('admin.coupons.toggle', $coupon) }}', {
                                        method: 'PATCH',
                                        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}
                                    }).then(r => r.json()).then(d => { active = d.is_active })"
                                    :class="active
                                        ? 'border-green-600/30 bg-green-50 text-green-700'
                                        : 'border-rule bg-ivory-alt text-ink-muted'"
                                    class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider cursor-pointer transition-colors"
                                    x-text="active ? 'Active' : 'Inactive'">
                                </button>
                            </td>
                            <td class="text-right pr-5">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('admin.coupons.show', $coupon) }}"
                                       class="bp-btn-ghost gap-1 text-[10px]">
                                        <x-heroicon-o-eye class="w-3.5 h-3.5" />
                                        View
                                    </a>
                                    <a href="{{ route('admin.coupons.edit', $coupon) }}"
                                       class="bp-btn-ghost gap-1 text-[10px]">
                                        <x-heroicon-o-pencil-square class="w-3.5 h-3.5" />
                                        Edit
                                    </a>
                                    <form action="{{ route('admin.coupons.destroy', $coupon) }}" method="POST"
                                          class="inline" onsubmit="return confirm('Delete this coupon?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="bp-btn-ghost text-red-600 hover:text-red-700 text-[10px]">
                                            <x-heroicon-o-trash class="w-3.5 h-3.5" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-16 text-center">
                                <x-heroicon-o-ticket class="w-10 h-10 mx-auto text-ink/20 mb-3" />
                                <p class="font-display font-bold text-ink">No coupons yet</p>
                                <p class="mt-1 text-sm text-ink-muted">Create your first discount coupon.</p>
                                <a href="{{ route('admin.coupons.create') }}" class="bp-btn-primary mt-5 inline-flex">
                                    <x-heroicon-o-plus class="w-4 h-4" />
                                    New Coupon
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($coupons->hasPages())
            <div class="px-5 py-4 border-t border-rule">
                {{ $coupons->withQueryString()->links() }}
            </div>
        @endif
    </section>

</div>
@endsection
