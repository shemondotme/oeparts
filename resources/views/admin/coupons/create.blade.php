@extends('layouts.admin')

@section('title', 'New Coupon')
@section('page_title', 'New Coupon')

@section('header_actions')
    <a href="{{ route('admin.coupons.index') }}" class="bp-btn-ghost gap-1">
        <x-heroicon-o-arrow-left class="w-4 h-4" />
        Back to Coupons
    </a>
@endsection

@section('content')
<div class="max-w-2xl space-y-6">

    @if($errors->any())
    <div class="border border-red-600/30 bg-red-50 p-4">
        <p class="bp-spec text-red-600 mb-2">§ Validation · Errors</p>
        <ul class="space-y-1">
            @foreach($errors->all() as $error)
                <li class="font-mono text-xs text-red-700">— {{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <section class="bp-card overflow-hidden">
        <header class="bp-card-header">
            <p class="bp-spec text-amber-ink">§ Marketing · New Coupon</p>
            <h2 class="mt-1 font-display text-xl font-bold text-ink tracking-[-0.02em]">
                Create Discount Code<span class="text-amber">.</span>
            </h2>
        </header>

        <form method="POST" action="{{ route('admin.coupons.store') }}">
            @csrf
            <div class="divide-y divide-rule">

                {{-- Code + Name --}}
                <div class="p-5 space-y-4">
                    <p class="bp-spec text-ink-muted">§ Identity</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block bp-spec mb-2">§ Code <span class="text-red-500">*</span></label>
                            <input type="text" name="code" value="{{ old('code') }}" required
                                   placeholder="e.g. SUMMER20"
                                   class="bp-input-mono w-full uppercase">
                            @error('code')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block bp-spec mb-2">§ Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" value="{{ old('name') }}" required
                                   placeholder="e.g. Summer Sale 2026"
                                   class="bp-input w-full">
                            @error('name')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Discount --}}
                <div class="p-5 space-y-4">
                    <p class="bp-spec text-ink-muted">§ Discount · Rules</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block bp-spec mb-2">§ Discount Type <span class="text-red-500">*</span></label>
                            <select name="discount_type" required class="bp-select">
                                @foreach($discountTypes as $type)
                                    <option value="{{ $type->value }}" {{ old('discount_type') === $type->value ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $type->value)) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('discount_type')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block bp-spec mb-2">§ Discount Value <span class="text-red-500">*</span></label>
                            <input type="text" inputmode="decimal" name="discount_value"
                                   value="{{ old('discount_value') }}" required
                                   placeholder="20.00"
                                   class="bp-input-mono w-full">
                            @error('discount_value')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block bp-spec mb-2">§ Min Order Amount</label>
                            <input type="text" inputmode="decimal" name="min_order_amount"
                                   value="{{ old('min_order_amount') }}"
                                   placeholder="0.00"
                                   class="bp-input-mono w-full">
                            @error('min_order_amount')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block bp-spec mb-2">§ Expires At</label>
                            <input type="date" name="expires_at" value="{{ old('expires_at') }}"
                                   class="bp-input w-full">
                            @error('expires_at')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block bp-spec mb-2">§ Total Usage Limit <span class="text-ink-muted font-normal normal-case">(empty = unlimited)</span></label>
                            <input type="number" name="usage_limit" value="{{ old('usage_limit') }}" min="1"
                                   placeholder="Unlimited"
                                   class="bp-input w-full">
                            @error('usage_limit')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block bp-spec mb-2">§ Per User Limit <span class="text-ink-muted font-normal normal-case">(empty = unlimited)</span></label>
                            <input type="number" name="usage_limit_per_user" value="{{ old('usage_limit_per_user') }}" min="1"
                                   placeholder="Unlimited"
                                   class="bp-input w-full">
                            @error('usage_limit_per_user')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Status --}}
                <div class="p-5 space-y-3">
                    <p class="bp-spec text-ink-muted">§ Status</p>
                    <input type="hidden" name="is_active" value="0">
                    <label class="flex items-center gap-2 text-sm text-ink cursor-pointer">
                        <input type="checkbox" id="is_active" name="is_active" value="1"
                               {{ old('is_active', '1') ? 'checked' : '' }}
                               class="rounded-none border-rule">
                        Active — coupon is valid and usable
                    </label>
                </div>

            </div>

            <div class="px-5 py-4 bg-ivory-alt border-t border-rule flex items-center justify-end gap-3">
                <a href="{{ route('admin.coupons.index') }}" class="bp-btn-ghost">Cancel</a>
                <button type="submit" class="bp-btn-primary">Create Coupon</button>
            </div>
        </form>
    </section>

</div>
@endsection
