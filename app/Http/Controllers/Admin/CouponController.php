<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DiscountType;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        $query = Coupon::with('creator')->latest();

        if ($request->filled('search')) {
            $query->where('code', 'like', '%' . $request->search . '%')
                  ->orWhere('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('is_active') && $request->is_active !== 'all') {
            $query->where('is_active', $request->is_active === 'active');
        }

        $coupons = $query->paginate(20)->withQueryString();

        return view('admin.coupons.index', [
            'coupons'       => $coupons,
            'discountTypes' => DiscountType::cases(),
        ]);
    }

    public function create()
    {
        return view('admin.coupons.create', [
            'discountTypes' => DiscountType::cases(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'                  => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'name'                  => ['required', 'string', 'max:100'],
            'discount_type'         => ['required', Rule::enum(DiscountType::class)],
            'discount_value'        => ['required', 'numeric', 'min:0'],
            'min_order_amount'      => ['nullable', 'numeric', 'min:0'],
            'usage_limit'           => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_user'  => ['nullable', 'integer', 'min:1'],
            'expires_at'            => ['nullable', 'date'],
            'is_active'             => ['boolean'],
        ]);

        $validated['created_by'] = auth('admin')->id();

        Coupon::create($validated);

        return redirect()->route('admin.coupons.index')
            ->with('success', 'Coupon created successfully.');
    }

    public function show(Coupon $coupon)
    {
        $coupon->load(['creator', 'usages']);

        return view('admin.coupons.show', ['coupon' => $coupon]);
    }

    public function edit(Coupon $coupon)
    {
        return view('admin.coupons.edit', [
            'coupon'        => $coupon,
            'discountTypes' => DiscountType::cases(),
        ]);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $validated = $request->validate([
            'code'                  => ['required', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($coupon->id)],
            'name'                  => ['required', 'string', 'max:100'],
            'discount_type'         => ['required', Rule::enum(DiscountType::class)],
            'discount_value'        => ['required', 'numeric', 'min:0'],
            'min_order_amount'      => ['nullable', 'numeric', 'min:0'],
            'usage_limit'           => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_user'  => ['nullable', 'integer', 'min:1'],
            'expires_at'            => ['nullable', 'date'],
            'is_active'             => ['boolean'],
        ]);

        $coupon->update($validated);

        return redirect()->route('admin.coupons.index')
            ->with('success', 'Coupon updated successfully.');
    }

    public function destroy(Coupon $coupon)
    {
        $coupon->delete();

        return redirect()->route('admin.coupons.index')
            ->with('success', 'Coupon deleted.');
    }

    public function toggle(\App\Models\Coupon $coupon): \Illuminate\Http\JsonResponse
    {
        $coupon->update(['is_active' => !$coupon->is_active]);
        return response()->json(['success' => true, 'is_active' => $coupon->is_active]);
    }
}
