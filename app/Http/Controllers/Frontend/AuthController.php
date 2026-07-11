<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use App\Enums\OtpPurpose;
use App\Jobs\SendOtpEmail;
use App\Jobs\SendWelcomeEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class AuthController extends Controller
{
    /**
     * Handle user login.
     */
    public function login(Request $request, string $lang)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('auth.validation_failed'),
                'errors' => $validator->errors(),
            ], 422);
        }

        // Rate limit login attempts (5 per 15 minutes per IP)
        $maxAttempts = (int) settings('security.login_max_attempts', 5);
        $decayMinutes = (int) settings('security.login_window_minutes', 15);
        if (! RateLimiter::attempt("login:{$request->ip()}", $maxAttempts, function () {
            return true;
        }, $decayMinutes * 60)) {
            throw new TooManyRequestsHttpException($decayMinutes * 60, __('auth.too_many_login_attempts', ['minutes' => $decayMinutes]));
        }

        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember', false);

        if (! Auth::guard('web')->attempt($credentials, $remember)) {
            return response()->json([
                'success' => false,
                'message' => __('auth.invalid_email_or_password'),
            ], 401);
        }

        $user = Auth::guard('web')->user();
        if (! $user->is_active) {
            Auth::guard('web')->logout();

            return response()->json([
                'success' => false,
                'message' => __('auth.account_deactivated'),
            ], 403);
        }

        if (is_null($user->email_verified_at)) {
            $otpService = app(OtpService::class);
            $otp = $otpService->generate($user->email, OtpPurpose::EmailVerify, $request->ip());
            dispatch(new SendOtpEmail($user->email, $otp->otp_code, $lang));

            Auth::guard('web')->logout();

            return response()->json([
                'success' => true,
                'message' => __('auth.email_verification_required'),
                'data' => [
                    'requires_otp' => true,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => __('auth.login_successful'),
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'token' => null,
            ],
        ]);
    }

    /**
     * Handle user registration.
     */
    public function register(Request $request, string $lang)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:30',
            'password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised()],
            'password_confirmation' => 'required|string',
            'agree_terms' => 'required|accepted',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('auth.validation_failed'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'password' => Hash::make($request->input('password')),
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        dispatch(new SendWelcomeEmail($user, $lang));

        $otpService = app(OtpService::class);
        $otp = $otpService->generate($user->email, OtpPurpose::EmailVerify, $request->ip());
        dispatch(new SendOtpEmail($user->email, $otp->otp_code, $lang));

        return response()->json([
            'success' => true,
            'message' => __('auth.registration_successful'),
            'data' => [
                'requires_otp' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
        ]);
    }

    /**
     * Handle user logout.
     */
    public function logout(Request $request, string $lang)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('auth.logged_out_successfully'),
            ]);
        }

        return redirect()->to("/{$lang}/")->with('status', __('auth.logged_out_successfully'));
    }

    /**
     * Verify OTP for email verification.
     */
    public function verifyOtp(Request $request, string $lang, OtpService $otpService)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|string|size:'.settings('auth.otp_length', 6),
            'purpose' => 'required|in:email_verify,guest_checkout,contact_form',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('auth.invalid_input'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $purpose = OtpPurpose::from($request->input('purpose'));
        $result = $otpService->verify(
            $request->input('email'),
            $request->input('otp'),
            $purpose
        );

        if ($result !== OtpService::RESULT_OK) {
            return response()->json([
                'success' => false,
                'message' => $otpService->message($result),
                'reason' => $result,
            ], 422);
        }

        // If purpose is email_verify, mark user as verified
        if ($purpose === OtpPurpose::EmailVerify) {
            $user = User::where('email', $request->input('email'))->first();
            if ($user && ! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }
        }

        return response()->json([
            'success' => true,
            'message' => __('auth.otp_verified_successfully'),
        ]);
    }

    /**
     * Resend OTP.
     */
    public function resendOtp(Request $request, string $lang, OtpService $otpService)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'purpose' => 'required|in:email_verify,guest_checkout,contact_form',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('auth.invalid_input'),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $purpose = OtpPurpose::from($request->input('purpose'));
            $otp = $otpService->generate($request->input('email'), $purpose, $request->ip());
            dispatch(new SendOtpEmail($request->input('email'), $otp->otp_code, $lang));

            return response()->json([
                'success' => true,
                'message' => __('auth.otp_resent_successfully'),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 429); // Too Many Requests
        }
    }
}
