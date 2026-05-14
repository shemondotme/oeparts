@extends('layouts.admin')

@section('title', 'Customers')
@section('page_title', 'Customer Management')

@section('content')
<div class="space-y-6">

    {{-- Filters --}}
    <section class="bp-card">
        <header class="bp-card-header">
            <p class="bp-spec text-ink-muted">§ Filter · Customers</p>
        </header>
        <form method="GET" action="{{ route('admin.customers.index') }}"
              class="p-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label for="search" class="block bp-spec mb-2">§ Search</label>
                <input type="text" id="search" name="search" value="{{ request('search') }}"
                       placeholder="Name or email..."
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
                <a href="{{ route('admin.customers.index') }}" class="bp-btn-ghost">Reset</a>
                <button type="submit" class="bp-btn-primary">Apply</button>
            </div>
        </form>
    </section>

    {{-- Flash message --}}
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
                <p class="bp-spec text-amber-ink">§ Customers · Registry</p>
                <h2 class="mt-1 font-display text-xl font-bold text-ink tracking-[-0.02em]">
                    All Customers<span class="text-amber">.</span>
                </h2>
            </div>
            <p class="font-mono text-xs text-ink-muted tabular-nums">
                {{ number_format($customers->total()) }} records
            </p>
        </header>

        <div class="overflow-x-auto">
            <table class="bp-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Registered</th>
                        <th>Status</th>
                        <th class="text-right pr-5">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                        <tr class="{{ $customer->trashed() ? 'opacity-60' : '' }} cursor-pointer"
                            data-href="{{ route('admin.customers.show', $customer) }}"
                            onclick="if(!event.target.closest('a,button,form')) window.location.href=this.dataset.href">
                            <td>
                                <p class="text-sm font-bold text-ink">{{ $customer->name }}</p>
                                <p class="font-mono text-xs text-ink-muted mt-0.5">{{ $customer->email }}</p>
                            </td>
                            <td>
                                <p class="font-mono text-xs tabular-nums text-ink">{{ $customer->phone ?? '—' }}</p>
                            </td>
                            <td>
                                <p class="font-mono text-xs tabular-nums text-ink">{{ $customer->created_at->format('Y-m-d') }}</p>
                            </td>
                            <td>
                                @if($customer->trashed())
                                    <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border-red-600/30 bg-red-50 text-red-700">Deleted</span>
                                @elseif($customer->is_active)
                                    <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border-green-600/30 bg-green-50 text-green-700">Active</span>
                                @else
                                    <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border-rule bg-ivory-alt text-ink-muted">Inactive</span>
                                @endif
                            </td>
                            <td class="text-right pr-5">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('admin.customers.show', $customer) }}"
                                       class="bp-btn-ghost gap-1 text-[10px]">
                                        <x-heroicon-o-eye class="w-3.5 h-3.5" />
                                        View
                                    </a>
                                    @if(!$customer->trashed())
                                        <form action="{{ route('admin.customers.toggle-active', $customer) }}" method="POST" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="bp-btn-ghost gap-1 text-[10px] {{ $customer->is_active ? 'text-amber-700' : 'text-green-700' }}"
                                                    title="{{ $customer->is_active ? 'Deactivate' : 'Activate' }}">
                                                @if($customer->is_active)
                                                    <x-heroicon-o-lock-closed class="w-3.5 h-3.5" />
                                                    Lock
                                                @else
                                                    <x-heroicon-o-lock-open class="w-3.5 h-3.5" />
                                                    Unlock
                                                @endif
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-16 text-center">
                                <x-heroicon-o-users class="w-10 h-10 mx-auto text-ink/20 mb-3" />
                                <p class="font-display font-bold text-ink">No customers found</p>
                                <p class="mt-1 text-sm text-ink-muted">Try adjusting your filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($customers->hasPages())
            <div class="px-5 py-4 border-t border-rule">
                {{ $customers->withQueryString()->links() }}
            </div>
        @endif
    </section>

</div>
@endsection
