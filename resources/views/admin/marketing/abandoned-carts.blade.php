@extends('layouts.admin')

@section('title', 'Abandoned Carts')
@section('page_title', 'Abandoned Carts')

@section('header_actions')
    <a href="{{ route('admin.marketing.abandoned-carts.export') }}" class="bp-btn-outline gap-1">
        <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
        Export CSV
    </a>
@endsection

@section('content')
<div class="space-y-6">

    {{-- Flash --}}
    @if(session('success'))
    <div class="flex items-center gap-3 border border-green-600/30 bg-green-50 px-4 py-3">
        <x-heroicon-o-check-circle class="w-5 h-5 text-green-600 flex-shrink-0" />
        <p class="text-sm text-green-700">{{ session('success') }}</p>
    </div>
    @endif
    @if(session('error'))
    <div class="flex items-center gap-3 border border-red-600/30 bg-red-50 px-4 py-3">
        <x-heroicon-o-x-circle class="w-5 h-5 text-red-600 flex-shrink-0" />
        <p class="text-sm text-red-700">{{ session('error') }}</p>
    </div>
    @endif

    {{-- Filters --}}
    <section class="bp-card">
        <header class="bp-card-header">
            <p class="bp-spec text-ink-muted">§ Filter · Abandoned Carts</p>
        </header>
        <form method="GET" action="{{ route('admin.marketing.abandoned-carts.index') }}"
              class="p-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label for="email" class="block bp-spec mb-2">§ Email</label>
                <input type="email" id="email" name="email" value="{{ request('email') }}"
                       placeholder="customer@example.com"
                       class="bp-input">
            </div>
            <div>
                <label for="status" class="block bp-spec mb-2">§ Recovery Status</label>
                <select id="status" name="status" class="bp-select">
                    <option value="">All</option>
                    <option value="pending"   {{ request('status') === 'pending'   ? 'selected' : '' }}>Pending Recovery</option>
                    <option value="recovered" {{ request('status') === 'recovered' ? 'selected' : '' }}>Recovery Sent</option>
                </select>
            </div>
            <div class="md:col-span-2 xl:col-span-2 flex items-center justify-end gap-4 pt-2 border-t border-rule md:border-0 md:pt-0 self-end">
                <a href="{{ route('admin.marketing.abandoned-carts.index') }}" class="bp-btn-ghost">Reset</a>
                <button type="submit" class="bp-btn-primary">Apply</button>
            </div>
        </form>
    </section>

    {{-- Table --}}
    <section class="bp-card overflow-hidden">
        <header class="bp-card-header flex items-center justify-between gap-4">
            <div>
                <p class="bp-spec text-amber-ink">§ Marketing · Abandoned Carts</p>
                <h2 class="mt-1 font-display text-xl font-bold text-ink tracking-[-0.02em]">
                    Recovery Queue<span class="text-amber">.</span>
                </h2>
            </div>
            <p class="font-mono text-xs text-ink-muted tabular-nums">
                {{ number_format($carts->total()) }} records
            </p>
        </header>

        <div class="overflow-x-auto">
            <table class="bp-table">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Customer</th>
                        <th>Cart Total</th>
                        <th>Last Active</th>
                        <th>Recovery</th>
                        <th class="text-right pr-5">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($carts as $cart)
                        <tr>
                            <td>
                                <p class="font-mono text-xs text-ink">
                                    {{ $cart->guest_email ?? $cart->user?->email ?? 'N/A' }}
                                </p>
                            </td>
                            <td>
                                <p class="text-sm text-ink">{{ $cart->user?->name ?? 'Guest' }}</p>
                            </td>
                            <td>
                                <p class="font-mono text-sm tabular-nums font-bold text-ink">
                                    {{ format_money($cart->cart_snapshot['total'] ?? 0) }}
                                </p>
                            </td>
                            <td>
                                <p class="font-mono text-xs text-ink-muted">{{ $cart->last_active_at->diffForHumans() }}</p>
                            </td>
                            <td>
                                @if($cart->recovery_email_sent)
                                    <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border-green-600/30 bg-green-50 text-green-700">Sent</span>
                                @else
                                    <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border-amber/30 bg-amber-50 text-amber-700">Pending</span>
                                @endif
                            </td>
                            <td class="text-right pr-5">
                                @if(!$cart->recovery_email_sent)
                                    <form method="POST" action="{{ route('admin.marketing.abandoned-carts.send-recovery', $cart) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="bp-btn-ghost gap-1 text-[10px]">
                                            <x-heroicon-o-paper-airplane class="w-3.5 h-3.5" />
                                            Send Recovery
                                        </button>
                                    </form>
                                @else
                                    <span class="font-mono text-xs text-ink-muted">Already sent</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-16 text-center">
                                <x-heroicon-o-shopping-cart class="w-10 h-10 mx-auto text-ink/20 mb-3" />
                                <p class="font-display font-bold text-ink">No abandoned carts found</p>
                                <p class="mt-1 text-sm text-ink-muted">Try adjusting your filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($carts->hasPages())
            <div class="px-5 py-4 border-t border-rule">
                {{ $carts->withQueryString()->links() }}
            </div>
        @endif
    </section>

</div>
@endsection
