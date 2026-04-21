@extends('layouts.admin')

@section('title', 'New Coupon')

@section('content')
<div class="px-6 py-8 max-w-2xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">New Coupon</h1>
    </div>

    @if($errors->any())
        <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
            <ul class="text-sm text-red-700 space-y-1 list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.coupons.store') }}" class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
        @csrf

        <div class="grid grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Code <span class="text-red-500">*</span></label>
                <input type="text" name="code" value="{{ old('code') }}" required
                       class="w-full rounded-lg border-gray-300 text-sm font-mono uppercase"
                       placeholder="SUMMER20">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full rounded-lg border-gray-300 text-sm"
                       placeholder="Summer Sale 2026">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Discount Type <span class="text-red-500">*</span></label>
                <select name="discount_type" required class="w-full rounded-lg border-gray-300 text-sm">
                    @foreach($discountTypes as $type)
                        <option value="{{ $type->value }}" {{ old('discount_type') === $type->value ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $type->value)) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Discount Value <span class="text-red-500">*</span></label>
                <input type="text" inputmode="decimal" name="discount_value" value="{{ old('discount_value') }}" required
                       class="w-full rounded-lg border-gray-300 text-sm" placeholder="20.00">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Min Order Amount</label>
                <input type="text" inputmode="decimal" name="min_order_amount" value="{{ old('min_order_amount') }}"
                       class="w-full rounded-lg border-gray-300 text-sm" placeholder="0.00">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Expires At</label>
                <input type="date" name="expires_at" value="{{ old('expires_at') }}"
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Total Usage Limit</label>
                <input type="number" name="usage_limit" value="{{ old('usage_limit') }}" min="1"
                       class="w-full rounded-lg border-gray-300 text-sm" placeholder="Unlimited">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Per User Limit</label>
                <input type="number" name="usage_limit_per_user" value="{{ old('usage_limit_per_user') }}" min="1"
                       class="w-full rounded-lg border-gray-300 text-sm" placeholder="Unlimited">
            </div>
        </div>

        <div class="flex items-center gap-3">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" id="is_active" name="is_active" value="1"
                   {{ old('is_active', '1') ? 'checked' : '' }}
                   class="rounded border-gray-300 text-[#0B3A68]">
            <label for="is_active" class="text-sm font-medium text-gray-700">Active</label>
        </div>

        <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
            <a href="{{ route('admin.coupons.index') }}"
               class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50">Cancel</a>
            <button type="submit"
                    class="px-4 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
                Create Coupon
            </button>
        </div>
    </form>
</div>
@endsection
