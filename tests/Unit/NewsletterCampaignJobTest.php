<?php

namespace Tests\Unit;

use App\Jobs\SendNewsletterCampaign;
use App\Mail\NewsletterCampaignEmail;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NewsletterCampaignJobTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Queue dispatch
    // -------------------------------------------------------------------------

    #[Test]
    public function campaign_job_is_dispatched_on_default_queue(): void
    {
        Queue::fake();
        $campaign = NewsletterCampaign::factory()->create();

        dispatch(new SendNewsletterCampaign($campaign));

        Queue::assertPushedOn('default', SendNewsletterCampaign::class);
    }

    // -------------------------------------------------------------------------
    // handle() — email dispatch
    // -------------------------------------------------------------------------

    #[Test]
    public function handle_sends_email_to_every_active_subscriber(): void
    {
        Mail::fake();

        NewsletterSubscriber::factory()->count(3)->create(['is_active' => true]);
        NewsletterSubscriber::factory()->create(['is_active' => false]);

        $campaign = NewsletterCampaign::factory()->create(['status' => 'draft']);

        $job = new SendNewsletterCampaign($campaign);
        $job->handle();

        Mail::assertQueued(NewsletterCampaignEmail::class, 3);
    }

    #[Test]
    public function handle_skips_inactive_subscribers(): void
    {
        Mail::fake();

        NewsletterSubscriber::factory()->count(2)->create(['is_active' => false]);
        $campaign = NewsletterCampaign::factory()->create(['status' => 'draft']);

        $job = new SendNewsletterCampaign($campaign);
        $job->handle();

        Mail::assertNothingQueued();
    }

    // -------------------------------------------------------------------------
    // handle() — status transitions
    // -------------------------------------------------------------------------

    #[Test]
    public function handle_sets_status_to_sent_after_completion(): void
    {
        Mail::fake();

        NewsletterSubscriber::factory()->count(2)->create(['is_active' => true]);
        $campaign = NewsletterCampaign::factory()->create(['status' => 'draft']);

        $job = new SendNewsletterCampaign($campaign);
        $job->handle();

        $this->assertDatabaseHas('newsletter_campaigns', [
            'id'     => $campaign->id,
            'status' => 'sent',
        ]);
    }

    #[Test]
    public function handle_sets_status_to_sent_with_no_subscribers(): void
    {
        Mail::fake();
        $campaign = NewsletterCampaign::factory()->create(['status' => 'draft']);

        $job = new SendNewsletterCampaign($campaign);
        $job->handle();

        $this->assertDatabaseHas('newsletter_campaigns', [
            'id'     => $campaign->id,
            'status' => 'sent',
        ]);
    }

    #[Test]
    public function handle_increments_sent_count_per_subscriber(): void
    {
        Mail::fake();

        NewsletterSubscriber::factory()->count(5)->create(['is_active' => true]);
        $campaign = NewsletterCampaign::factory()->create(['status' => 'draft', 'sent_count' => 0]);

        $job = new SendNewsletterCampaign($campaign);
        $job->handle();

        $this->assertDatabaseHas('newsletter_campaigns', [
            'id'         => $campaign->id,
            'sent_count' => 5,
        ]);
    }

    // -------------------------------------------------------------------------
    // handle() — recipient tracking
    // -------------------------------------------------------------------------

    #[Test]
    public function handle_creates_recipient_records_for_each_subscriber(): void
    {
        Mail::fake();

        $subscribers = NewsletterSubscriber::factory()->count(3)->create(['is_active' => true]);
        $campaign = NewsletterCampaign::factory()->create(['status' => 'draft']);

        $job = new SendNewsletterCampaign($campaign);
        $job->handle();

        $this->assertDatabaseCount('newsletter_campaign_recipients', 3);

        foreach ($subscribers as $subscriber) {
            $this->assertDatabaseHas('newsletter_campaign_recipients', [
                'campaign_id'   => $campaign->id,
                'subscriber_id' => $subscriber->id,
                'email'         => $subscriber->email,
            ]);
        }
    }

    #[Test]
    public function handle_records_sent_at_timestamp_on_recipients(): void
    {
        Mail::fake();

        NewsletterSubscriber::factory()->create(['is_active' => true]);
        $campaign = NewsletterCampaign::factory()->create(['status' => 'draft']);

        $job = new SendNewsletterCampaign($campaign);
        $job->handle();

        $this->assertDatabaseMissing('newsletter_campaign_recipients', [
            'campaign_id' => $campaign->id,
            'sent_at'     => null,
        ]);
    }

    // -------------------------------------------------------------------------
    // Eloquent relationship FK correctness (Marketing module audit, Option W):
    // NewsletterCampaign::recipients(), NewsletterCampaignRecipient::campaign()/
    // subscriber(), and NewsletterSubscriber::campaignRecipients() all used
    // hasMany()/belongsTo() with no explicit foreign key, so Laravel guessed
    // 'newsletter_campaign_id'/'newsletter_subscriber_id' — but the migration
    // uses the short 'campaign_id'/'subscriber_id' columns. Every prior test
    // here only asserted via raw assertDatabaseHas(), never through the actual
    // relationship methods, so this was never caught — it crashed
    // NewsletterSubscriberResource's entire index page (withCount(
    // 'campaignRecipients')) and NewsletterCampaignResource's Recipients tab.

    #[Test]
    public function campaign_recipients_relationship_resolves_correctly(): void
    {
        Mail::fake();

        $subscriber = NewsletterSubscriber::factory()->create(['is_active' => true]);
        $campaign = NewsletterCampaign::factory()->create(['status' => 'draft']);

        (new SendNewsletterCampaign($campaign))->handle();

        $this->assertCount(1, $campaign->recipients);
        $this->assertTrue($campaign->recipients->first()->subscriber->is($subscriber));
        $this->assertCount(1, $subscriber->campaignRecipients);
        $this->assertTrue($subscriber->campaignRecipients->first()->campaign->is($campaign));
    }

    // -------------------------------------------------------------------------
    // Retry safety (Marketing module audit, Critical): a job retry after a
    // mid-run failure (tries = 3) used to re-select every active subscriber
    // from scratch with no idempotency guard, re-creating recipient rows and
    // re-queueing a send even for subscribers a prior, partially-completed
    // attempt had already emailed.
    // -------------------------------------------------------------------------

    #[Test]
    public function a_retry_does_not_re_email_a_subscriber_already_marked_sent(): void
    {
        Mail::fake();

        $alreadySent = NewsletterSubscriber::factory()->create(['is_active' => true]);
        $notYetSent = NewsletterSubscriber::factory()->create(['is_active' => true]);
        $campaign = NewsletterCampaign::factory()->create(['status' => 'sending']);

        \App\Models\NewsletterCampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => $alreadySent->id,
            'email' => $alreadySent->email,
            'status' => 'sent',
            'sent_at' => now()->subMinute(),
        ]);

        // Simulates the job being retried after a mid-run failure.
        (new SendNewsletterCampaign($campaign))->handle();

        Mail::assertQueued(NewsletterCampaignEmail::class, 1);
        Mail::assertQueued(NewsletterCampaignEmail::class, function ($mail) use ($notYetSent) {
            return $mail->hasTo($notYetSent->email);
        });
        $this->assertDatabaseCount('newsletter_campaign_recipients', 2);
    }

    #[Test]
    public function a_retry_re_attempts_a_subscriber_whose_prior_row_was_only_pending_or_failed(): void
    {
        Mail::fake();

        $stuckPending = NewsletterSubscriber::factory()->create(['is_active' => true]);
        $campaign = NewsletterCampaign::factory()->create(['status' => 'sending']);

        \App\Models\NewsletterCampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => $stuckPending->id,
            'email' => $stuckPending->email,
            'status' => 'pending',
        ]);

        (new SendNewsletterCampaign($campaign))->handle();

        Mail::assertQueued(NewsletterCampaignEmail::class, 1);
        $this->assertDatabaseHas('newsletter_campaign_recipients', [
            'campaign_id' => $campaign->id,
            'subscriber_id' => $stuckPending->id,
            'status' => 'sent',
        ]);
        // upsert(), not insert(): the pre-existing row is reused, not
        // duplicated (which would otherwise collide with the new unique
        // (campaign_id, subscriber_id) index).
        $this->assertDatabaseCount('newsletter_campaign_recipients', 1);
    }

    #[Test]
    public function final_sent_count_reflects_all_attempts_not_just_the_last_retry(): void
    {
        Mail::fake();

        $fromEarlierRun = NewsletterSubscriber::factory()->create(['is_active' => true]);
        $fromThisRun = NewsletterSubscriber::factory()->create(['is_active' => true]);
        $campaign = NewsletterCampaign::factory()->create(['status' => 'sending']);

        \App\Models\NewsletterCampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => $fromEarlierRun->id,
            'email' => $fromEarlierRun->email,
            'status' => 'sent',
            'sent_at' => now()->subMinute(),
        ]);

        (new SendNewsletterCampaign($campaign))->handle();

        $this->assertDatabaseHas('newsletter_campaigns', [
            'id' => $campaign->id,
            'sent_count' => 2,
        ]);
    }
}
