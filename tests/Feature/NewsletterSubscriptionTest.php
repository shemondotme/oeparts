<?php

namespace Tests\Feature;

use App\Jobs\SendNewsletterConfirmationEmail;
use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NewsletterSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    // Regression test for the Marketing module audit (Option W): NewsletterController
    // used to `dispatch(new SendNewsletterConfirmation($subscriber))` — a class that
    // never existed — so every new-subscriber signup threw a fatal Error before
    // dispatch() was even reached. Queue::fake() does not protect against this: the
    // `new SendNewsletterConfirmation(...)` expression is evaluated before dispatch()
    // is called, so this test would have failed with the original bug regardless.

    #[Test]
    public function new_subscriber_signup_dispatches_confirmation_email_without_crashing(): void
    {
        Queue::fake();

        $response = $this->postJson('/en/newsletter/subscribe', [
            'email' => 'newsubscriber@example.com',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $subscriber = NewsletterSubscriber::where('email', 'newsubscriber@example.com')->first();
        $this->assertNotNull($subscriber);
        $this->assertFalse($subscriber->is_active);

        Queue::assertPushed(SendNewsletterConfirmationEmail::class, function (SendNewsletterConfirmationEmail $job) use ($subscriber) {
            return $job->subscriber->is($subscriber) && str_contains($job->confirmUrl, 'newsletter/confirm');
        });
    }

    #[Test]
    public function confirm_route_activates_pending_subscription(): void
    {
        $subscriber = NewsletterSubscriber::create([
            'email' => 'pending@example.com',
            'lang' => 'en',
            'is_active' => false,
            'subscribed_at' => now(),
            'ip_address' => '127.0.0.1',
            'unsubscribe_token' => 'test-confirm-token',
        ]);

        $response = $this->get('/en/newsletter/confirm/test-confirm-token');

        $response->assertRedirect();
        $this->assertTrue($subscriber->refresh()->is_active);
        $this->assertNull($subscriber->unsubscribed_at);
    }

    #[Test]
    public function confirm_route_with_invalid_token_does_not_crash(): void
    {
        $response = $this->get('/en/newsletter/confirm/no-such-token');

        $response->assertRedirect();
    }
}
