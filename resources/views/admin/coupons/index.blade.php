@extends('layouts.admin')

@section('title', 'Coupons')

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Coupons</h1>
            <p class="text-gray-600 mt-1">Manage discount codes and promotions</p>
        </div>
        <a href="{{ route('admin.coupons.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
            <x-heroicon-o-plus class="w-4 h-4" />
            New Coupon
        </a>
    </div>

    @if(session('success'))
        <div class="mb-6 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">
            <x-heroicon-o-check-circle class="w-5 h-5 shrink-0" />
            {{ session('success') }}
        </div>
    @endif

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 mb-6">
        <form method="GET" action="{{ route('admin.coupons.index') }}" class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Code or name..."
                       class="rounded-lg border-gray-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                <select name="is_active" class="rounded-lg border-gray-300 text-sm">
                    <option value="all">All</option>
                    <option value="active" {{ request('is_active') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('is_active') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.coupons.index') }}"
                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50">Reset</a>
                <button type="submit" class="px-3 py-2 bg-[#0B3A68] text-white rounded-lg text-sm hover:bg-blue-900">Filter</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Discount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usage</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expires</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($coupons as $coupon)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-mono text-sm font-semibold text-[#0B3A68]">{{ $coupon->code }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $coupon->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                @if($coupon->discount_type->value === 'percentage')
                                    {{ $coupon->discount_value }}%
                                @else
                                    €{{ number_format($coupon->discount_value, 2) }}
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $coupon->usages_count ?? 0 }} / {{ $coupon->usage_limit ?? '∞' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $coupon->expires_at ? $coupon->expires_at->format('M d, Y') : '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button
                                    x-data="{ active: {{ $coupon->is_active ? 'true' : 'false' }} }"
                                    x-on:click="fetch('{{ route('admin.coupons.toggle', $coupon) }}', {
                                        method: 'PATCH',
                                        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}
                                    }).then(r => r.json()).then(d => { active = d.is_active })"
                                    :class="active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                    class="px-2 py-0.5 rounded-full text-xs font-medium cursor-pointer"
                                    x-text="active ? 'Active' : 'Inactive'">
                                </button>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('admin.coupons.show', $coupon) }}" class="text-gray-500 hover:text-gray-900" title="View">
                                        <x-heroicon-o-eye class="w-4 h-4" />
                                    </a>
                                    <a href="{{ route('admin.coupons.edit', $coupon) }}" class="text-[#0B3A68] hover:text-blue-900" title="Edit">
                                        <x-heroicon-o-pencil-square class="w-4 h-4" />
                                    </a>
                                    <form action="{{ route('admin.coupons.destroy', $coupon) }}" method="POST"
                                          class="inline" onsubmit="return confirm('Delete this coupon?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-400 hover:text-red-600">
                                            <x-heroicon-o-trash class="w-4 h-4" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <x-heroicon-o-ticket class="w-12 h-12 mx-auto text-gray-300 mb-3" />
                                <p class="text-sm font-medium text-gray-900">No coupons yet</p>
                                <p class="text-xs text-gray-500 mt-1">Create your first discount coupon.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($coupons->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $coupons->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
