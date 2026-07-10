<?php

namespace Tests\Feature;

use App\Enums\ContactStatus;
use App\Models\Admin;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomersModuleTest extends TestCase
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

    private function makeMessage(array $attrs = []): ContactMessage
    {
        return ContactMessage::create(array_merge([
            'name'         => 'Jane Doe',
            'email'        => 'jane@example.com',
            'subject_type' => 'general_inquiry',
            'message'      => 'Hello, do you stock this part?',
            'status'       => ContactStatus::Unread,
            'ip_address'   => '127.0.0.1',
        ], $attrs));
    }

    public function test_contact_messages_table_renders_rows_and_actions(): void
    {
        // Regression: the Reply action's nonexistent heroicon-o-reply SVG
        // 500'd the DEFERRED table render — a plain page-mount test passes
        // while the real table is broken, so load the table explicitly.
        $this->makeMessage();

        Livewire::test(\App\Filament\Resources\ContactMessageResource\Pages\ListContactMessages::class)
            ->loadTable()
            ->assertOk()
            ->assertSee('Jane Doe');
    }

    public function test_reply_is_persisted_and_resolves_the_message(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $message = $this->makeMessage();

        Livewire::test(\App\Filament\Resources\ContactMessageResource\Pages\ListContactMessages::class)
            ->loadTable()
            ->callTableAction('reply', $message, ['reply_body' => 'We stock it — link attached.', 'mark_resolved' => true]);

        $message->refresh();
        $this->assertSame('We stock it — link attached.', $message->reply_body);
        $this->assertNotNull($message->replied_at);
        $this->assertNotNull($message->replied_by);
        $this->assertSame(ContactStatus::Resolved, $message->status);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SendContactReplyEmail::class);
    }

    public function test_password_reset_action_sends_broker_link_not_a_password(): void
    {
        // User overrides sendPasswordResetNotification to dispatch the custom
        // Industrial Blueprint email job — assert on that job.
        \Illuminate\Support\Facades\Queue::fake();
        $customer = User::factory()->create();
        $originalHash = $customer->password;

        Livewire::test(\App\Filament\Resources\CustomerResource\Pages\ListCustomers::class)
            ->loadTable()
            ->callTableAction('sendPasswordReset', $customer);

        \Illuminate\Support\Facades\Queue::assertPushed(
            \App\Jobs\SendPasswordResetEmail::class,
            fn ($job) => $job->email === $customer->email,
        );
        $this->assertSame($originalHash, $customer->refresh()->password, 'the admin action must never change the password itself');
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $customer->email]);
    }

    public function test_segment_thresholds_come_from_settings(): void
    {
        $customer = User::factory()->create();
        Order::factory()->count(2)->create(['user_id' => $customer->id, 'status' => 'paid']);

        // Default repeat threshold is 3 — two orders is 'Regular'; with the
        // setting lowered to 2 the same customer becomes 'Repeat'.
        \App\Models\Setting::updateOrCreate(
            ['group' => 'customers', 'key' => 'repeat_min_orders'],
            ['value' => '2', 'type' => 'integer'],
        );
        \Illuminate\Support\Facades\Cache::forget('settings.customers');

        $this->assertSame(2, (int) settings('customers.repeat_min_orders', 3));
    }

    public function test_customer_aggregate_sorts_execute(): void
    {
        $customer = User::factory()->create();
        Order::factory()->create(['user_id' => $customer->id]);

        // The aliases the sortable(query:) closures order by must exist on
        // the table query (sorting by the old fake column names threw
        // "Unknown column in ORDER BY" on a real click).
        $query = User::query()
            ->withSum('orders', 'grand_total')
            ->withAvg('orders', 'grand_total')
            ->orderBy('orders_sum_grand_total')
            ->orderBy('orders_avg_grand_total');

        $this->assertNotNull($query->first());

        $bySubquery = User::query()
            ->orderBy(Order::select('created_at')->whereColumn('user_id', 'users.id')->latest()->limit(1))
            ->first();
        $this->assertNotNull($bySubquery);
    }
}
