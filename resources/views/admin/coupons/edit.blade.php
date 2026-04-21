@extends('layouts.admin')

@section('title', 'Edit Coupon')

@section('content')
<div class="px-6 py-8 max-w-2xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Edit Coupon</h1>
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

    <form method="POST" action="{{ route('admin.coupons.update', $coupon) }}" class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Code <span class="text-red-500">*</span></label>
                <input type="text" name="code" value="{{ old('code', $coupon->code) }}" required
                       class="w-full rounded-lg border-gray-300 text-sm font-mono uppercase">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $coupon->name) }}" required
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Discount Type <span class="text-red-500">*</span></label>
                <select name="discount_type" required class="w-full rounded-lg border-gray-300 text-sm">
                    @foreach($discountTypes as $type)
                        <option value="{{ $type->value }}" {{ old('discount_type', $coupon->discount_type->value) === $type->value ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $type->value)) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Discount Value <span class="text-red-500">*</span></label>
                <input type="text" inputmode="decimal" name="discount_value" value="{{ old('discount_value', $coupon->discount_value) }}" required
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Min Order Amount</label>
                <input type="text" inputmode="decimal" name="min_order_amount" value="{{ old('min_order_amount', $coupon->min_order_amount) }}"
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Expires At</label>
                <input type="date" name="expires_at" value="{{ old('expires_at', $coupon->expires_at?->format('Y-m-d')) }}"
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Total Usage Limit</label>
                <input type="number" name="usage_limit" value="{{ old('usage_limit', $coupon->usage_limit) }}" min="1"
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Per User Limit</label>
                <input type="number" name="usage_limit_per_user" value="{{ old('usage_limit_per_user', $coupon->usage_limit_per_user) }}" min="1"
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>
        </div>

        <div class="flex items-center gap-3">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" id="is_active" name="is_active" value="1"
                   {{ old('is_active', $coupon->is_active) ? 'checked' : '' }}
                   class="rounded border-gray-300 text-[#0B3A68]">
            <label for="is_active" class="text-sm font-medium text-gray-700">Active</label>
        </div>

        <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
            <a href="{{ route('admin.coupons.index') }}"
               class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50">Cancel</a>
            <button type="submit"
                    class="px-4 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
                Save Changes
            </button>
        </div>
    </form>
</div>
@endsection
