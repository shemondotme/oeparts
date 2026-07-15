<?php

namespace Tests\Feature;

use App\Jobs\SendNewsletterCampaign;
use App\Models\Admin;
use App\Models\NewsletterCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class MarketingModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\RolesSeeder::class,
            \Database\Seeders\AdminSeeder::class,
        ]);

        $this->actingAs(Admin::where('email', 'superadmin@oeparts.test')->firstOrFail(), 'admin');
    }

    private function makeCampaign(array $attrs = []): NewsletterCampaign
    {
        return NewsletterCampaign::create(array_merge([
            'subject'      => 'Test Campaign',
            'html_content' => '<p>Hello</p>',
            'status'       => 'draft',
            'created_by'   => Admin::first()->id,
        ], $attrs));
    }

    public function test_campaign_create_view_and_edit_pages_render(): void
    {
        // Regression: Forms\Components\Section doesn't exist in Filament v5 —
        // the entire campaign authoring flow (create/view/edit) 500'd.
        $campaign = $this->makeCampaign();

        Livewire::test(\App\Filament\Resources\NewsletterCampaignResource\Pages\CreateNewsletterCampaign::class)
            ->assertOk();
        Livewire::test(\App\Filament\Resources\NewsletterCampaignResource\Pages\ViewNewsletterCampaign::class, ['record' => $campaign->id])
            ->assertOk();
        Livewire::test(\App\Filament\Resources\NewsletterCampaignResource\Pages\EditNewsletterCampaign::class, ['record' => $campaign->id])
            ->assertOk();
    }

    public function test_creating_a_campaign_saves_content_and_sets_status(): void
    {
        Livewire::test(\App\Filament\Resources\NewsletterCampaignResource\Pages\CreateNewsletterCampaign::class)
            ->fillForm([
                'subject'      => 'Spring Sale',
                'html_content' => '<p>Deals!</p>',
                'scheduled_at' => now()->addDay()->toDateTimeString(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $campaign = NewsletterCampaign::where('subject', 'Spring Sale')->first();
        $this->assertNotNull($campaign);
        $this->assertSame('<p>Deals!</p>', $campaign->html_content, 'statePath double-nesting would lose the content');
        $this->assertSame('scheduled', $campaign->status);
        $this->assertNotNull($campaign->created_by);
    }

    public function test_send_due_command_dispatches_only_due_unsent_campaigns(): void
    {
        Queue::fake();

        $due = $this->makeCampaign(['subject' => 'Due', 'status' => 'scheduled', 'scheduled_at' => now()->subMinute()]);
        $future = $this->makeCampaign(['subject' => 'Future', 'status' => 'scheduled', 'scheduled_at' => now()->addHour()]);
        $sent = $this->makeCampaign(['subject' => 'Sent', 'status' => 'sent', 'scheduled_at' => now()->subHour(), 'sent_at' => now()->subHour()]);
        $draftNoDate = $this->makeCampaign(['subject' => 'Draft']);

        $this->artisan('oeparts:newsletter:send-due')->assertSuccessful();

        Queue::assertPushed(SendNewsletterCampaign::class, 1);
        Queue::assertPushed(SendNewsletterCampaign::class, fn ($job) => $job->campaign->is($due));
    }

    public function test_unknown_mailables_log_as_other_and_inquiry_status_maps(): void
    {
        $listener = new \App\Listeners\LogEmailSent();
        $method = new \ReflectionMethod($listener, 'determineTemplateType');

        $this->assertSame(\App\Enums\EmailTemplate::Other, $method->invoke($listener, 'App\Mail\SomeFutureMailable'));
        $this->assertSame(\App\Enums\EmailTemplate::PartInquiryStatus, $method->invoke($listener, 'App\Mail\PartInquiryStatusUpdate'));
        $this->assertSame(\App\Enums\EmailTemplate::Other, $method->invoke($listener, null));
    }

    public function test_email_log_list_renders_new_template_types(): void
    {
        \App\Models\EmailLog::create([
            'to_email' => 'x@example.com',
            'subject' => 'Inquiry update',
            'template_type' => \App\Enums\EmailTemplate::PartInquiryStatus,
            'status' => \App\Enums\LogStatus::Success,
            'sent_at' => now(),
        ]);
        \App\Models\EmailLog::create([
            'to_email' => 'y@example.com',
            'subject' => 'Mystery mail',
            'template_type' => \App\Enums\EmailTemplate::Other,
            'status' => \App\Enums\LogStatus::Failed,
            'error_message' => 'smtp timeout',
            'sent_at' => now(),
        ]);

        Livewire::test(\App\Filament\Resources\EmailLogResource\Pages\ListEmailLogs::class)
            ->loadTable()
            ->assertOk()
            ->assertSee('Part Inquiry Status');
    }

    public function test_send_due_command_is_scheduled(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events());

        $this->assertTrue(
            $events->contains(fn ($event) => str_contains($event->command ?? '', 'oeparts:newsletter:send-due')),
            'the send-due command must be registered with the scheduler — otherwise "Scheduled Send Date" is a dead promise'
        );
    }

    /**
     * Regression: newsletter_subscribers.subscribed_at AND .ip_address are
     * both NOT NULL (migration 2026_03_26_100032) with no form field for
     * either and no mutateFormDataBeforeCreate hook — manually adding a
     * subscriber via the admin UI crashed with a raw SQLSTATE NOT NULL
     * constraint failure instead of saving, confirmed live. Same bug class
     * as CreateCoupon/CreatePage (missing-required-column-on-create).
     */
    public function test_subscriber_creation_sets_subscribed_at_and_ip_address(): void
    {
        Livewire::test(\App\Filament\Resources\NewsletterSubscriberResource\Pages\CreateNewsletterSubscriber::class)
            ->fillForm(['email' => 'new-subscriber@example.com', 'lang' => 'en', 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $subscriber = \App\Models\NewsletterSubscriber::where('email', 'new-subscriber@example.com')->first();
        $this->assertNotNull($subscriber, 'Subscriber creation failed');
        $this->assertNotNull($subscriber->subscribed_at);
        $this->assertNotNull($subscriber->ip_address);
    }
}
