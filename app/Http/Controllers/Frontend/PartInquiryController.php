<?php

namespace App\Http\Controllers\Frontend;

use App\Enums\PartInquiryStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SendPartInquiryNotification;
use App\Models\PartInquiry;
use App\Services\OemNormalizerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class PartInquiryController extends Controller
{
    public function store(Request $request, string $lang)
    {
        // Rate limit: 5 inquiries per hour per IP
        $maxInquiries = (int) settings('search.inquiry_max_per_email', 5);
        if (!RateLimiter::attempt("inquiry:{$request->ip()}", $maxInquiries, fn() => true, 3600)) {
            return response()->json([
                'success' => false,
                'message' => __('part_inquiry.rate_limited'),
            ], 429);
        }

        $validated = $request->validate([
            'email'        => ['required', 'email', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:50'],
            'oem_number'   => ['required', 'string', 'max:100'],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'car_model'    => ['nullable', 'string', 'max:100'],
            'year'         => ['nullable', 'string', 'max:10', 'regex:/^\d{4}$/'],
            'vin_number'   => ['nullable', 'string', 'max:50'],
            'quantity'     => ['nullable', 'integer', 'min:1', 'max:99'],
            'urgency'      => ['nullable', 'string', 'in:normal,soon,urgent'],
            'notes'        => ['nullable', 'string', 'max:500'],
            'website'      => ['max:0'], // honeypot
        ]);

        $inquiry = PartInquiry::create([
            'email'        => $validated['email'],
            'phone'        => $validated['phone'] ?? null,
            'oem_number'   => strtoupper(trim($validated['oem_number'])),
            'manufacturer' => $validated['manufacturer'] ?? null,
            'car_model'    => $validated['car_model'] ?? null,
            'year'         => $validated['year'] ?? null,
            'vin_number'   => $validated['vin_number'] ? strtoupper(trim($validated['vin_number'])) : null,
            'quantity'     => $validated['quantity'] ?? 1,
            'urgency'      => $validated['urgency'] ?? 'normal',
            'notes'        => $validated['notes'] ?? null,
            'status'       => PartInquiryStatus::New,
            'ip_address'   => $request->ip(),
        ]);

        dispatch(new SendPartInquiryNotification($inquiry));

        $hours = (int) settings('part_inquiry.response_hours', 24);

        return response()->json([
            'success' => true,
            'message' => __('part_inquiry.submitted', ['hours' => $hours]),
        ]);
    }
}
