<?php

namespace App\Http\Controllers\Frontend;

use App\Enums\OtpPurpose;
use App\Events\ContactMessageReceived;
use App\Http\Controllers\Controller;
use App\Http\Requests\Frontend\ContactFormRequest;
use App\Models\ContactMessage;
use App\Services\OtpService;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    /**
     * Display contact form page.
     */
    public function show(string $lang)
    {
        return view('frontend.contact.show');
    }

    /**
     * Submit contact form.
     *
     * OTP-gated (PRD: "/{lang}/contact — Contact (OTP-gated)"): the email must
     * already have been verified via the generic /verify-otp endpoint
     * (purpose=contact_form) before this endpoint will persist the message —
     * see the emailVerified widget in frontend.contact.show's Blade/JS.
     */
    public function submit(ContactFormRequest $request)
    {
        $otpService = app(OtpService::class);
        $otpRequired = $otpService->enabled();

        if ($otpRequired && ! $otpService->isRecentlyVerified($request->email, OtpPurpose::ContactForm)) {
            return response()->json([
                'success' => false,
                'message' => __('contact.otp_invalid'),
                'data' => ['requires_otp' => true],
            ], 422);
        }

        $contactMessage = ContactMessage::create([
            'email' => $request->email,
            'name' => $request->name,
            'subject_type' => $request->subject_type,
            'order_number' => $request->order_number,
            'oem_number' => $request->oem_number,
            'manufacturer' => $request->manufacturer,
            'car_model' => $request->car_model,
            'year' => $request->year,
            'vin_number' => $request->vin_number,
            'message' => $request->message,
            'status' => 'unread',
            'otp_verified' => $otpRequired,
            'ip_address' => $request->ip(),
        ]);

        // Best-effort — on a 'sync' queue connection (rule #41: many installs
        // run without a worker) this notification's mail send happens inline,
        // so a real SMTP failure would otherwise throw right here and 500 a
        // submission whose ContactMessage row is already safely persisted.
        try {
            event(new ContactMessageReceived(
                $contactMessage->name,
                $contactMessage->email,
                $contactMessage->subject_type->value,
                $contactMessage->message
            ));
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['success' => true, 'message' => settings_trans('contact.success_message', __('contact.sent_success'))]);
    }
}
