@extends('layouts.admin')

@section('title', 'Coupon: ' . $coupon->code)

@section('content')
<div class="px-6 py-8 max-w-2xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 font-mono">{{ $coupon->code }}</h1>
        <div class="flex gap-2">
            <a href="{{ route('admin.coupons.edit', $coupon) }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
                <x-heroicon-o-pencil-square class="w-4 h-4" />
                Edit
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
        @foreach([
            ['Name', $coupon->name],
            ['Type', ucfirst(str_replace('_', ' ', $coupon->discount_type->value))],
            ['Value', $coupon->discount_type->value === 'percentage' ? $coupon->discount_value . '%' : '€' . number_format($coupon->discount_value, 2)],
            ['Min Order', $coupon->min_order_amount ? '€' . number_format($coupon->min_order_amount, 2) : '—'],
            ['Usage Limit', $coupon->usage_limit ?? '∞'],
            ['Per User Limit', $coupon->usage_limit_per_user ?? '∞'],
            ['Expires', $coupon->expires_at ? $coupon->expires_at->format('M d, Y') : '—'],
            ['Created By', $coupon->creator?->name ?? '—'],
        ] as [$label, $value])
        <div class="flex px-6 py-4">
            <span class="w-40 text-sm font-medium text-gray-500">{{ $label }}</span>
            <span class="text-sm text-gray-900">{{ $value }}</span>
        </div>
        @endforeach
        <div class="flex px-6 py-4">
            <span class="w-40 text-sm font-medium text-gray-500">Status</span>
            @if($coupon->is_active)
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
            @else
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Inactive</span>
            @endif
        </div>
    </div>

    <div class="mt-4">
        <a href="{{ route('admin.coupons.index') }}" class="text-sm text-[#0B3A68] hover:underline">
            ← Back to Coupons
        </a>
    </div>
</div>
@endsection
