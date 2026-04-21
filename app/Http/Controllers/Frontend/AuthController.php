<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Jobs\SendOtpEmail;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class AuthController extends Controller
{
    /**
     * Handle user login.
     */
    public function login(Request $request, string $lang)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Rate limit login attempts (5 per 15 minutes per IP)
        $maxAttempts = (int) settings('security.login_max_attempts', 5);
        $decayMinutes = (int) settings('security.login_window_minutes', 15);
        if (!RateLimiter::attempt("login:{$request->ip()}", $maxAttempts, function () {
            return true;
        }, $decayMinutes * 60)) {
            throw new TooManyRequestsHttpException($decayMinutes * 60, 'Too many login attempts. Please try again in ' . $decayMinutes . ' minutes.');
        }

        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember', false);

        if (!Auth::guard('web')->attempt($credentials, $remember)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        $user = Auth::guard('web')->user();
        if (!$user->is_active) {
            Auth::guard('web')->logout();
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated.',
            ], 403);
        }

        // If email is not verified, generate OTP and require verification
        if (!$user->email_verified_at) {
            try {
                $otpService = app(OtpService::class);
                $otp = $otpService->generate($user->email, \App\Enums\OtpPurpose::EmailVerify, $request->ip());
                dispatch(new SendOtpEmail($user->email, $otp->otp_code, $lang));
            } catch (\RuntimeException $e) {
                // Cooldown active - still return requires_otp
            }

            return response()->json([
                'success' => true,
                'message' => 'Email verification required.',
                'data'    => [
                    'requires_otp' => true,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data'    => [
                'user' => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                ],
                'token' => null, // Session-based auth, no token
            ],
        ]);
    }

    /**
     * Handle user registration.
     */
    public function register(Request $request, string $lang, OtpService $otpService)
    {
        $validator = Validator::make($request->all(), [
            'name'                  => 'required|string|max:200',
            'email'                 => 'required|email|unique:users,email',
            'phone'                 => 'nullable|string|max:30',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
            'agree_terms'           => 'required|accepted',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name'     => $request->input('name'),
            'email'    => $request->input('email'),
            'phone'    => $request->input('phone'),
            'password' => Hash::make($request->input('password')),
            'is_active' => true,
        ]);

        // Generate OTP for email verification and dispatch email
        try {
            $otp = $otpService->generate($user->email, \App\Enums\OtpPurpose::EmailVerify, $request->ip());
            dispatch(new SendOtpEmail($user->email, $otp->otp_code, $lang));
        } catch (\RuntimeException $e) {
            // Cooldown active – user created but OTP not sent; frontend handles this scenario
        }

        // Log the user in automatically after registration
        Auth::guard('web')->login($user);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Please verify your email.',
            'data'    => [
                'requires_otp' => true,
                'user' => [
                    'id'    => $user->id,
                    'name'  => $user->name,
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

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Verify OTP for email verification.
     */
    public function verifyOtp(Request $request, string $lang, OtpService $otpService)
    {
        $validator = Validator::make($request->all(), [
            'email'   => 'required|email',
            'otp'     => 'required|string|size:' . settings('auth.otp_length', 6),
            'purpose' => 'required|in:email_verify,guest_checkout,contact_form',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $purpose = \App\Enums\OtpPurpose::from($request->input('purpose'));
        $result = $otpService->verify(
            $request->input('email'),
            $request->input('otp'),
            $purpose
        );

        if ($result !== OtpService::RESULT_OK) {
            return response()->json([
                'success' => false,
                'message' => $otpService->message($result),
                'reason'  => $result,
            ], 400);
        }

        // If purpose is email_verify, mark user as verified
        if ($purpose === \App\Enums\OtpPurpose::EmailVerify) {
            $user = User::where('email', $request->input('email'))->first();
            if ($user && !$user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully.',
        ]);
    }

    /**
     * Resend OTP.
     */
    public function resendOtp(Request $request, string $lang, OtpService $otpService)
    {
        $validator = Validator::make($request->all(), [
            'email'   => 'required|email',
            'purpose' => 'required|in:email_verify,guest_checkout,contact_form',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $purpose = \App\Enums\OtpPurpose::from($request->input('purpose'));
            $otp = $otpService->generate($request->input('email'), $purpose, $request->ip());
            dispatch(new SendOtpEmail($request->input('email'), $otp->otp_code, $lang));
            return response()->json([
                'success' => true,
                'message' => 'OTP resent successfully.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 429); // Too Many Requests
        }
    }
}