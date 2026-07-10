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

    public function test_send_due_command_is_scheduled(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events());

        $this->assertTrue(
            $events->contains(fn ($event) => str_contains($event->command ?? '', 'oeparts:newsletter:send-due')),
            'the send-due command must be registered with the scheduler — otherwise "Scheduled Send Date" is a dead promise'
        );
    }
}
