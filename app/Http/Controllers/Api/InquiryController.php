<?php

namespace App\Http\Controllers\Api;

use App\Enums\PartInquiryStatus;
use App\Http\Controllers\Controller;
use App\Models\PartInquiry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class InquiryController extends Controller
{
    /**
     * Store a part inquiry from the homepage modal form.
     * POST /api/inquiry
     */
    public function store(Request $request)
    {
        // Rate limit: 5 inquiries per hour per IP
        $maxInquiries = (int) settings('security.inquiry_max_per_email', 5);
        if (!RateLimiter::attempt("inquiry:{$request->ip()}", $maxInquiries, fn() => true, 3600)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many inquiries. Please try again later.',
            ], 429);
        }

        $validated = $request->validate([
            'oem_number'    => ['required', 'string', 'max:100'],
            'email'         => ['required', 'email', 'max:255'],
            'vehicle_make'  => ['nullable', 'string', 'max:100'],
            'vehicle_model' => ['nullable', 'string', 'max:100'],
            'vehicle_year'  => ['nullable', 'string', 'max:10', 'regex:/^\d{4}$/'],
            'notes'         => ['nullable', 'string', 'max:500'],
            'website'       => ['max:0'], // honeypot
            'lang'          => ['nullable', 'string'],
        ]);

        PartInquiry::create([
            'email'        => $validated['email'],
            'phone'        => null,
            'oem_number'   => strtoupper(trim($validated['oem_number'])),
            'manufacturer' => $validated['vehicle_make'] ?? null,
            'car_model'    => $validated['vehicle_model'] ?? null,
            'year'         => $validated['vehicle_year'] ?? null,
            'vin_number'   => null,
            'quantity'     => 1,
            'urgency'      => 'normal',
            'notes'        => $validated['notes'] ?? null,
            'status'       => PartInquiryStatus::New,
            'ip_address'   => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your inquiry has been submitted. We\'ll respond within 24 hours.',
        ]);
    }
}
