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
     *
     * $lang must come before $provider: Laravel's ControllerDispatcher binds
     * route parameters positionally (array_values(), not by parameter name),
     * in the order they're captured from the URI. The {lang} prefix group
     * captures before the route's own {provider} segment, so a (provider,
     * lang) signature silently received them swapped — every "Log in with
     * Google" click 404'd into "Unsupported social provider." instead of
     * ever reaching Socialite.
     */
    public function redirect(string $lang, string $provider)
    {
        if (!in_array($provider, ['google', 'facebook'])) {
            return redirect()->route('frontend.home', ['lang' => $lang])
                ->with('error', 'Unsupported social provider.');
        }

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the callback from Google/Facebook.
     *
     * $lang before $provider — see the matching comment on redirect().
     */
    public function callback(string $lang, string $provider)
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

            // Google explicitly reports whether the email on the account was
            // verified (email_verified in the OIDC claims); trust that flag
            // when the provider gives one. Facebook's Graph API doesn't
            // expose an equivalent field, but only ever returns a
            // platform-confirmed email in the first place, so absence of the
            // flag is not itself a signal of an unverified address.
            $emailVerified = $socialUser->user['email_verified'] ?? $socialUser->user['verified_email'] ?? true;
            if (! filter_var($emailVerified, FILTER_VALIDATE_BOOLEAN)) {
                return redirect()->route('frontend.home', ['lang' => $lang])
                    ->with('error', 'Your ' . ucfirst($provider) . ' email address is not verified. Please verify it with ' . ucfirst($provider) . ' and try again.');
            }

            $user = User::where('email', $email)->first();

            if (!$user) {
                if (! filter_var(settings('auth.registration_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
                    return redirect()->route('frontend.home', ['lang' => $lang])
                        ->with('error', __('auth.registration_disabled'));
                }

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

            if (! $user->is_active) {
                return redirect()->route('frontend.home', ['lang' => $lang])
                    ->with('error', __('auth.account_deactivated'));
            }

            Auth::guard('web')->login($user, true);

            // Rotate the session ID on every successful authentication — see
            // the matching comment in AuthController::login(). Not injecting
            // Request here: Laravel's ControllerDispatcher passes route
            // parameters positionally (array_values(), not by name), and a
            // typed Request param shifts $provider/$lang out of position.
            session()->regenerate();

            return redirect()->route('frontend.home', ['lang' => $lang])
                ->with('success', 'Logged in successfully via ' . ucfirst($provider) . '.');

        } catch (\Exception $e) {
            \Log::error("Social login failed ({$provider}): " . $e->getMessage());

            return redirect()->route('frontend.home', ['lang' => $lang])
                ->with('error', 'Social login failed. Please try again.');
        }
    }
}
