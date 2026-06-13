<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * Redirect to Google/Facebook for authentication.
     */
    public function redirect(string $provider, string $lang)
    {
        if (!in_array($provider, ['google', 'facebook'])) {
            return redirect()->route('frontend.home', ['lang' => $lang])
                ->with('error', 'Unsupported social provider.');
        }

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the callback from Google/Facebook.
     */
    public function callback(string $provider, string $lang)
    {
        if (!in_array($provider, ['google', 'facebook'])) {
            return redirect()->route('frontend.home', ['lang' => $lang])
                ->with('error', 'Unsupported social provider.');
        }

        try {
            $socialUser = Socialite::driver($provider)->user();

            $email = $socialUser->getEmail();
            if (!$email) {
                return redirect()->route('frontend.home', ['lang' => $lang])
                    ->with('error', 'Could not retrieve email from social account.');
            }

            $user = User::where('email', $email)->first();

            if (!$user) {
                $user = User::create([
                    'name'              => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
                    'email'             => $email,
                    'password'          => Hash::make(Str::random(32)),
                    'email_verified_at' => now(),
                    'is_active'         => true,
                    'phone'             => null,
                    'language'          => $lang,
                ]);
            }

            Auth::guard('web')->login($user, true);

            return redirect()->route('frontend.home', ['lang' => $lang])
                ->with('success', 'Logged in successfully via ' . ucfirst($provider) . '.');

        } catch (\Exception $e) {
            \Log::error("Social login failed ({$provider}): " . $e->getMessage());

            return redirect()->route('frontend.home', ['lang' => $lang])
                ->with('error', 'Social login failed. Please try again.');
        }
    }
}
