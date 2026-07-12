<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Jobs\SendNewsletterConfirmationEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class NewsletterController extends Controller
{
    /**
     * Subscribe to newsletter.
     */
    public function subscribe(Request $request, string $lang)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'lang' => 'nullable|in:en,de,lt,fr,es',
        ]);

        // Rate limit: max subscriptions per hour per IP
        $maxSubscriptions = (int) settings('newsletter.rate_limit_per_hour', 3);
        $windowSeconds = (int) settings('newsletter.rate_window_seconds', 3600);
        if (!RateLimiter::attempt("newsletter:{$request->ip()}", $maxSubscriptions, function () {
            return true;
        }, $windowSeconds)) {
            throw new TooManyRequestsHttpException(3600, 'Too many subscription attempts. Please try again later.');
        }

        $email = $validated['email'];
        $locale = $validated['lang'] ?? $lang;
        $unsubscribeToken = hash_hmac('sha256', $email, config('app.key'));

        // Check if already subscribed
        $existing = NewsletterSubscriber::where('email', $email)->first();

        if ($existing && $existing->is_active) {
            return response()->json([
                'success' => false,
                'message' => __('You are already subscribed to our newsletter.'),
            ], 422);
        }

        if ($existing) {
            // Reactivate subscription
            $existing->update([
                'is_active' => true,
                'lang' => $locale,
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
                'ip_address' => $request->ip(),
            ]);

            $subscriber = $existing;
        } else {
            $doubleOptIn = filter_var(settings('newsletter.double_opt_in', true), FILTER_VALIDATE_BOOLEAN);

            // Create new subscription (requires confirmation unless double opt-in is disabled)
            $subscriber = NewsletterSubscriber::create([
                'email' => $email,
                'lang' => $locale,
                'is_active' => ! $doubleOptIn,
                'subscribed_at' => now(),
                'ip_address' => $request->ip(),
                'unsubscribe_token' => $unsubscribeToken,
            ]);

            if ($doubleOptIn) {
                // Send confirmation email
                $confirmUrl = route('frontend.newsletter.confirm', ['lang' => $locale, 'token' => $unsubscribeToken]);
                dispatch(new SendNewsletterConfirmationEmail($subscriber, $confirmUrl, $locale));
            }
        }

        return response()->json([
            'success' => true,
            'message' => $subscriber->is_active
                ? __('Thank you for subscribing to our newsletter!')
                : __('Please check your email to confirm your subscription.'),
        ]);
    }

    /**
     * Unsubscribe from newsletter.
     */
    public function unsubscribe(Request $request, string $lang, string $token)
    {
        $subscriber = NewsletterSubscriber::where('unsubscribe_token', $token)->first();

        if (!$subscriber) {
            return redirect()->route('frontend.home', compact('lang'))
                ->with('error', __('Invalid unsubscribe link.'));
        }

        $subscriber->update([
            'is_active' => false,
            'unsubscribed_at' => now(),
        ]);

        return redirect()->route('frontend.home', compact('lang'))
            ->with('success', __('You have been unsubscribed from our newsletter.'));
    }

    /**
     * Confirm a pending newsletter subscription (double opt-in).
     */
    public function confirm(Request $request, string $lang, string $token)
    {
        $subscriber = NewsletterSubscriber::where('unsubscribe_token', $token)->first();

        if (!$subscriber) {
            return redirect()->route('frontend.home', compact('lang'))
                ->with('error', __('Invalid confirmation link.'));
        }

        $subscriber->update([
            'is_active' => true,
            'unsubscribed_at' => null,
        ]);

        return redirect()->route('frontend.home', compact('lang'))
            ->with('success', __('Your newsletter subscription has been confirmed!'));
    }
}
