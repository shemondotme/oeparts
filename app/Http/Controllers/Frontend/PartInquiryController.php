<?php

namespace App\Http\Controllers\Frontend;

use App\Enums\PartInquiryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Frontend\PartInquiryRequest;
use App\Jobs\SendPartInquiryNotification;
use App\Models\FailedSearchLog;
use App\Models\PartInquiry;
use App\Services\OemNormalizerService;
use Illuminate\Http\Request;

class PartInquiryController extends Controller
{
    public function store(PartInquiryRequest $request, string $lang)
    {
        if (! auth()->check() && ! filter_var(settings('part_inquiry.guest_inquiries_allowed', true), FILTER_VALIDATE_BOOLEAN)) {
            return response()->json([
                'success' => false,
                'message' => __('part_inquiry.guest_not_allowed'),
            ], 403);
        }

        $validated = $request->validated();

        // Lifetime cap per email address — distinct from the per-IP-per-hour
        // throttle already enforced by the 'throttle:part_inquiry.rate_limit_per_hour'
        // route middleware (see routes/web.php).
        $maxPerEmail = (int) settings('security.inquiry_max_per_email', 10);
        if ($maxPerEmail > 0 && PartInquiry::where('email', $validated['email'])->count() >= $maxPerEmail) {
            return response()->json([
                'success' => false,
                'message' => __('part_inquiry.rate_limited'),
            ], 429);
        }

        $inquiry = PartInquiry::create([
            'failed_search_log_id' => $validated['failed_search_log_id'] ?? null,
            'email'        => $validated['email'],
            'phone'        => $validated['phone'] ?? null,
            'oem_number'   => strtoupper(trim($validated['oem_number'])),
            'manufacturer' => $validated['manufacturer'] ?? null,
            'car_model'    => $validated['car_model'] ?? null,
            'year'         => $validated['year'] ?? null,
            'vin_number'   => !empty($validated['vin_number']) ? strtoupper(trim($validated['vin_number'])) : null,
            'quantity'     => $validated['quantity'] ?? 1,
            'urgency'      => $validated['urgency'] ?? 'normal',
            'notes'        => $validated['notes'] ?? null,
            'status'       => PartInquiryStatus::New,
            'ip_address'   => $request->ip(),
        ]);

        if (! empty($validated['failed_search_log_id'])) {
            FailedSearchLog::where('id', $validated['failed_search_log_id'])->update(['inquiry_submitted' => true]);
        }

        // Best-effort — on a 'sync' queue connection (rule #41: many installs
        // run without a worker) this job's mail send happens inline, so a real
        // SMTP failure would otherwise throw right here and 500 a submission
        // whose PartInquiry row is already safely persisted.
        try {
            dispatch(new SendPartInquiryNotification($inquiry));
        } catch (\Throwable $e) {
            report($e);
        }

        $hours = (int) settings('part_inquiry.response_hours', 24);

        return response()->json([
            'success' => true,
            'message' => __('part_inquiry.submitted', ['hours' => $hours]),
        ]);
    }
}
