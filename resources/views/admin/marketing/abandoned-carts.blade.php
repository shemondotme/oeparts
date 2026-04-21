@extends('layouts.admin')

@section('title', 'Abandoned Carts')

@section('content')
<div class="px-6 py-8">
    <div class="max-w-7xl mx-auto">
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Abandoned Carts</h1>
                    <p class="text-slate-600 mt-2">Track and recover abandoned shopping carts.</p>
                </div>
                <a href="{{ route('admin.marketing.abandoned-carts.export') }}"
                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-navy rounded-lg hover:bg-navy/90">
                    <x-heroicon-o-arrow-down-tray class="w-4 h-4 mr-2" />
                    Export CSV
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-6 rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-6 rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.marketing.abandoned-carts') }}" class="mb-6">
            <div class="flex gap-4">
                <input type="email" name="email" value="{{ request('email') }}" placeholder="Search by email..."
                       class="flex-1 rounded-lg border border-slate-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy">
                <select name="status" class="rounded-lg border border-slate-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy">
                    <option value="">All Status</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending Recovery</option>
                    <option value="recovered" {{ request('status') === 'recovered' ? 'selected' : '' }}>Recovery Sent</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-navy text-white rounded-lg hover:bg-navy/90">Filter</button>
            </div>
        </form>

        {{-- Table --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Cart Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Last Active</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Recovery</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-slate-200">
                        @forelse($carts as $cart)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4 text-sm text-slate-900">
                                    {{ $cart->guest_email ?? $cart->user?->email ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">
                                    {{ $cart->user?->name ?? 'Guest' }}
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-navy">
                                    €{{ number_format($cart->cart_snapshot['total'] ?? 0, 2) }}
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">
                                    {{ $cart->last_active_at->diffForHumans() }}
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="px-2 py-1 text-xs font-medium rounded {{ $cart->recovery_email_sent ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $cart->recovery_email_sent ? 'Sent' : 'Pending' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    @if(!$cart->recovery_email_sent)
                                        <form method="POST" action="{{ route('admin.marketing.abandoned-carts.send-recovery', $cart) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-navy hover:text-navy/80">Send Recovery</button>
                                        </form>
                                    @else
                                        <span class="text-slate-400">Already sent</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-slate-500">
                                    No abandoned carts found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($carts->hasPages())
                <div class="px-6 py-4 border-t">{{ $carts->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
