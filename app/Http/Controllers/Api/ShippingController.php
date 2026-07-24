<?php

namespace App\Http\Controllers\Api;

use App\Models\ShippingMethod;
use Illuminate\Http\JsonResponse;

class ShippingController extends BaseApiController
{
    /**
     * GET /api/v1/shipping-methods
     */
    public function index(): JsonResponse
    {
        $methods = ShippingMethod::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => trans_field($m->name),
                'description' => trans_field($m->description),
                'flat_rate' => $m->flat_rate,
                'free_shipping_threshold' => $m->free_shipping_threshold,
                'estimated_days_min' => $m->estimated_days_min,
                'estimated_days_max' => $m->estimated_days_max,
            ]);

        return $this->successResponse($methods);
    }
}
