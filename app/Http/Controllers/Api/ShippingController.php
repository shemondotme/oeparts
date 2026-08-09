<?php

namespace App\Http\Controllers\Api;

use App\Models\ShippingMethod;
use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingController extends BaseApiController
{
    public function __construct(
        private ShippingService $shippingService
    ) {}

    /**
     * GET /api/shipping-methods?country_code=DE
     *
     * Without country_code, every active method across every zone is
     * returned — this previously had no way to narrow the list at all, so a
     * mobile client showed shipping options that don't actually serve the
     * customer's destination (e.g. a Baltic-only flat rate offered to a
     * customer shipping to Spain). country_code is optional so existing
     * callers that don't yet pass it keep working.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country_code' => 'nullable|string|size:2',
        ]);

        $methods = !empty($validated['country_code'])
            ? $this->shippingService->getMethodsForCountry($validated['country_code'])
            : ShippingMethod::where('is_active', true)->orderBy('sort_order')->get();

        return $this->successResponse($methods->map(fn ($m) => [
            'id' => $m->id,
            'name' => trans_field($m->name),
            'description' => trans_field($m->description),
            'flat_rate' => $m->flat_rate,
            'free_shipping_threshold' => $m->free_shipping_threshold,
            'estimated_days_min' => $m->estimated_days_min,
            'estimated_days_max' => $m->estimated_days_max,
        ]));
    }
}
