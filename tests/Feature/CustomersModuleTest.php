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
