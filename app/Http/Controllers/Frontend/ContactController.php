<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Frontend\ContactFormRequest;
use App\Jobs\SendOtpEmail;
use App\Models\ContactMessage;
use App\Models\Otp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

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
     * Send OTP for contact form verification.
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Rate limit: max 10 contact forms per email per hour
        $maxPerEmail = (int) settings('security.inquiry_max_per_email', 10);
        if (!RateLimiter::attempt("contact:{$request->email}", $maxPerEmail, function () {
            return true;
        }, 3600)) { // 1 hour = 3600 seconds
            throw new TooManyRequestsHttpException(3600, 'Too many contact form submissions. Please try again later.');
        }

        $email = $request->email;
        $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Delete any existing OTPs for this email
        Otp::where('email', $email)
            ->where('purpose', 'contact_form')
            ->delete();

        // Create new OTP
        Otp::create([
            'email' => $email,
            'otp_code' => $otpCode,
            'purpose' => 'contact_form',
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
            'ip_address' => $request->ip(),
        ]);

        // Dispatch OTP email (3rd arg is locale, not purpose)
        dispatch(new SendOtpEmail($email, $otpCode, app()->getLocale()));

        return response()->json(['success' => true, 'message' => 'Verification code sent to your email']);
    }

    /**
     * Verify OTP for contact form.
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
        ]);

        $otp = Otp::where('email', $request->email)
            ->where('otp_code', $request->otp)
            ->where('purpose', 'contact_form')
            ->where('expires_at', '>', now())
            ->whereNull('verified_at')
            ->first();

        if (!$otp) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired verification code'], 422);
        }

        // Mark as verified
        $otp->update(['verified_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Email verified successfully']);
    }

    /**
     * Submit contact form.
     */
    public function submit(ContactFormRequest $request)
    {
        // Check if OTP was verified for this email
        $verifiedOtp = Otp::where('email', $request->email)
            ->where('purpose', 'contact_form')
            ->whereNotNull('verified_at')
            ->where('created_at', '>', now()->subMinutes(30))
            ->first();

        if (!$verifiedOtp) {
            return response()->json(['success' => false, 'message' => 'Please verify your email first'], 422);
        }

        // Create contact message
        $message = ContactMessage::create([
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
            'otp_verified' => true,
            'ip_address' => $request->ip(),
        ]);

        // Clear the OTP after successful submission
        $verifiedOtp->delete();

        // Dispatch contact reply email (auto-reply to customer)
        // dispatch(new SendContactReplyEmail($message));

        return response()->json(['success' => true, 'message' => 'Your message has been sent successfully. We will get back to you soon.']);
    }
}
