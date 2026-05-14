@extends('layouts.admin')

@section('title', 'Coupon — ' . $coupon->code)
@section('page_title', 'Coupon Details')

@section('header_actions')
    <a href="{{ route('admin.coupons.edit', $coupon) }}" class="bp-btn-outline gap-1">
        <x-heroicon-o-pencil-square class="w-4 h-4" />
        Edit
    </a>
    <a href="{{ route('admin.coupons.index') }}" class="bp-btn-ghost gap-1">
        <x-heroicon-o-arrow-left class="w-4 h-4" />
        Back
    </a>
@endsection

@section('content')
<div class="max-w-2xl space-y-6">

    {{-- Header strip --}}
    <div class="bp-card p-5 flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="bp-spec text-ink-muted">§ Coupon</p>
            <h2 class="font-mono text-2xl font-bold text-amber-ink tracking-wider">
                {{ $coupon->code }}<span class="text-ink">.</span>
            </h2>
            <p class="text-sm text-ink-muted mt-0.5">{{ $coupon->name }}</p>
        </div>
        @if($coupon->is_active)
            <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border-green-600/30 bg-green-50 text-green-700">Active</span>
        @else
            <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border-rule bg-ivory-alt text-ink-muted">Inactive</span>
        @endif
    </div>

    <section class="bp-card overflow-hidden">
        <header class="bp-card-header">
            <p class="bp-spec text-ink-muted">§ Coupon · Details</p>
        </header>
        <div class="divide-y divide-rule">
            @foreach([
                ['§ Name',           $coupon->name,                                                false],
                ['§ Discount Type',  ucfirst(str_replace('_', ' ', $coupon->discount_type->value)), false],
                ['§ Discount Value', $coupon->discount_type->value === 'percentage'
                    ? $coupon->discount_value . '%'
                    : format_money($coupon->discount_value),                                        true],
                ['§ Min Order',      $coupon->min_order_amount ? format_money($coupon->min_order_amount) : '—', true],
                ['§ Usage Limit',    $coupon->usage_limit ?? '∞',                                  true],
                ['§ Per User Limit', $coupon->usage_limit_per_user ?? '∞',                         true],
                ['§ Expires',        $coupon->expires_at ? $coupon->expires_at->format('Y-m-d') : '—', true],
                ['§ Created By',     $coupon->creator?->name ?? '—',                               false],
            ] as [$label, $value, $mono])
            <div class="flex items-start gap-4 px-5 py-3">
                <span class="w-36 flex-shrink-0 bp-spec text-ink-muted">{{ $label }}</span>
                <span class="{{ $mono ? 'font-mono text-sm tabular-nums' : 'text-sm' }} text-ink">{{ $value }}</span>
            </div>
            @endforeach
            <div class="flex items-start gap-4 px-5 py-3">
                <span class="w-36 flex-shrink-0 bp-spec text-ink-muted">§ Status</span>
                @if($coupon->is_active)
                    <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border-green-600/30 bg-green-50 text-green-700">Active</span>
                @else
                    <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border-rule bg-ivory-alt text-ink-muted">Inactive</span>
                @endif
            </div>
        </div>
    </section>

    <section class="bp-card overflow-hidden">
        <header class="bp-card-header">
            <p class="bp-spec text-ink-muted">§ Metadata</p>
        </header>
        <div class="p-5 space-y-3">
            <div>
                <p class="bp-spec text-ink-muted mb-1">§ Created</p>
                <p class="font-mono text-xs tabular-nums text-ink">{{ $coupon->created_at->format('Y-m-d H:i') }}</p>
            </div>
            <div class="border-t border-rule pt-3">
                <p class="bp-spec text-ink-muted mb-1">§ Updated</p>
                <p class="font-mono text-xs tabular-nums text-ink">{{ $coupon->updated_at->format('Y-m-d H:i') }}</p>
            </div>
        </div>
    </section>

</div>
@endsection
