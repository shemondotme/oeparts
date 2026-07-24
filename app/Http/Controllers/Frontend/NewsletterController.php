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
            'website' => 'max:0',
        ]);

        // Rate limit: max subscriptions per hour per IP
        $maxSubscriptions = (int) settings('newsletter.rate_limit_per_hour', 3);
        $windowSeconds = (int) settings('newsletter.rate_window_seconds', 3600);
        if (!RateLimiter::attempt("newsletter:{$request->ip()}", $maxSubscriptions, function () {
            return true;
        }, $windowSeconds)) {
            throw new TooManyRequestsHttpException(3600, __('newsletter.rate_limited'));
        }

        $email = $validated['email'];
        $locale = $validated['lang'] ?? $lang;
        $unsubscribeToken = hash_hmac('sha256', $email, config('app.key'));
        $doubleOptIn = filter_var(settings('newsletter.double_opt_in', true), FILTER_VALIDATE_BOOLEAN);

        $existing = NewsletterSubscriber::where('email', $email)->first();

        if ($existing && $existing->is_active) {
            return response()->json([
                'success' => false,
                'message' => __('newsletter.already_subscribed'),
            ], 422);
        }

        if ($existing) {
            // Reactivate subscription — still gated by double_opt_in, same as a
            // brand-new subscription, so a previously-unsubscribed address can't
            // skip re-confirmation.
            $existing->update([
                'is_active' => ! $doubleOptIn,
                'lang' => $locale,
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
                'ip_address' => $request->ip(),
                'unsubscribe_token' => $existing->unsubscribe_token ?: $unsubscribeToken,
            ]);

            $subscriber = $existing;

            if ($doubleOptIn) {
                $confirmUrl = route('frontend.newsletter.confirm', ['lang' => $locale, 'token' => $subscriber->unsubscribe_token]);
                // Best-effort — a 'sync' queue connection (rule #41) runs this
                // inline, so a real SMTP failure must not 500 a subscription
                // that already saved successfully.
                try {
                    dispatch(new SendNewsletterConfirmationEmail($subscriber, $confirmUrl, $locale));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        } else {
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
                // Send confirmation email — best-effort, see the comment above.
                $confirmUrl = route('frontend.newsletter.confirm', ['lang' => $locale, 'token' => $unsubscribeToken]);
                try {
                    dispatch(new SendNewsletterConfirmationEmail($subscriber, $confirmUrl, $locale));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => $subscriber->is_active
                ? __('newsletter.subscribed_active')
                : __('newsletter.subscribed_pending'),
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
                ->with('error', __('newsletter.invalid_unsubscribe_link'));
        }

        $subscriber->update([
            'is_active' => false,
            'unsubscribed_at' => now(),
        ]);

        return redirect()->route('frontend.home', compact('lang'))
            ->with('success', __('newsletter.unsubscribed_success'));
    }

    /**
     * Confirm a pending newsletter subscription (double opt-in).
     */
    public function confirm(Request $request, string $lang, string $token)
    {
        $subscriber = NewsletterSubscriber::where('unsubscribe_token', $token)->first();

        if (!$subscriber) {
            return redirect()->route('frontend.home', compact('lang'))
                ->with('error', __('newsletter.invalid_confirmation_link'));
        }

        $subscriber->update([
            'is_active' => true,
            'unsubscribed_at' => null,
        ]);

        return redirect()->route('frontend.home', compact('lang'))
            ->with('success', __('newsletter.confirmed_success'));
    }
}
