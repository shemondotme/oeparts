<?php

namespace App\Http\Controllers\Api;

use App\Models\PartInquiry;
use App\Enums\PartInquiryStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class B2bController extends ApiController
{
    /**
     * POST /api/v1/b2b-request
     */
    public function store(Request $request): JsonResponse
    {
        // Rate limit: 3 requests per hour per IP
        if (!RateLimiter::attempt("b2b:{$request->ip()}", 3, fn () => true, 3600)) {
            return $this->errorResponse('Too many requests. Please try again later.', null, 429);
        }

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'contact_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'vat_number' => 'required|string|max:20',
            'country_code' => 'required|string|size:2',
            'message' => 'nullable|string|max:1000',
            'website' => 'max:0', // honeypot
        ]);

        PartInquiry::create([
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'oem_number' => 'B2B_APP',
            'manufacturer' => $validated['company_name'],
            'car_model' => $validated['contact_name'],
            'year' => null,
            'vin_number' => null,
            'quantity' => 0,
            'urgency' => 'normal',
            'notes' => json_encode([
                'type' => 'b2b_application',
                'vat_number' => $validated['vat_number'],
                'country_code' => $validated['country_code'],
                'message' => $validated['message'] ?? null,
            ]),
            'status' => PartInquiryStatus::New,
            'ip_address' => $request->ip(),
        ]);

        return $this->createdResponse(null, 'B2B application submitted. We will review and respond within 48 hours.');
    }
}
