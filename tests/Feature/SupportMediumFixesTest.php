<?php

namespace Tests\Feature;

use App\Enums\ContactSubjectType;
use App\Enums\RefundStatus;
use App\Events\PartInquiryReceived;
use App\Filament\Resources\ContactMessageResource;
use App\Filament\Resources\RefundRequestResource\Pages\EditRefundRequest;
use App\Models\Admin;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SupportMediumFixesTest extends TestCase
{
    use RefreshDatabase;

    // ── support-1: global search result title is meaningful, not the generic model label ──

    #[Test]
    public function contact_message_global_search_title_includes_name_and_subject(): void
    {
        $message = ContactMessage::create([
            'email' => 'jane@example.com', 'name' => 'Jane Doe',
            'subject_type' => ContactSubjectType::OrderIssue, 'message' => 'Where is my order?',
            'status' => 'unread', 'ip_address' => '127.0.0.1',
        ]);

        $title = ContactMessageResource::getGlobalSearchResultTitle($message);

        $this->assertStringContainsString('Jane Doe', $title);
        $this->assertStringContainsString('Order Issue', $title);
    }

    // ── support-4: new part inquiries actually notify admins in-panel ──

    #[Test]
    public function submitting_a_part_inquiry_dispatches_part_inquiry_received(): void
    {
        Event::fake([PartInquiryReceived::class]);

        $this->postJson('/en/inquiry', [
            'email' => 'buyer@example.com',
            'oem_number' => '04L115399F',
            'quantity' => 1,
            'urgency' => 'normal',
        ])->assertOk();

        Event::assertDispatched(PartInquiryReceived::class, function (PartInquiryReceived $event) {
            return $event->oemNumber === '04L115399F' && $event->customerEmail === 'buyer@example.com';
        });
    }

    #[Test]
    public function active_admins_receive_a_database_notification_for_a_new_part_inquiry(): void
    {
        $admin = Admin::factory()->create(['is_active' => true]);

        $this->postJson('/en/inquiry', [
            'email' => 'buyer2@example.com',
            'oem_number' => '04L115399G',
            'quantity' => 1,
            'urgency' => 'normal',
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $admin->id,
            'notifiable_type' => Admin::class,
        ]);
    }

    // ── support-7: refund amount_requested's cap follows the LIVE selected order, not a stale $record->order ──

    #[Test]
    public function refund_amount_requested_is_capped_at_the_currently_selected_orders_total(): void
    {
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'grand_total' => '50.00']);
        $refund = RefundRequest::factory()->create([
            'order_id' => $order->id, 'user_id' => $user->id,
            'status' => RefundStatus::Pending, 'amount_requested' => '50.00',
        ]);

        Livewire::test(EditRefundRequest::class, ['record' => $refund->getRouteKey()])
            ->fillForm(['amount_requested' => '999.00'])
            ->call('save')
            ->assertHasFormErrors(['amount_requested']);
    }

    #[Test]
    public function refund_amount_requested_within_the_order_total_saves_fine(): void
    {
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'grand_total' => '50.00']);
        $refund = RefundRequest::factory()->create([
            'order_id' => $order->id, 'user_id' => $user->id,
            'status' => RefundStatus::Pending, 'amount_requested' => '30.00',
        ]);

        Livewire::test(EditRefundRequest::class, ['record' => $refund->getRouteKey()])
            ->fillForm(['amount_requested' => '45.00'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals('45.00', $refund->fresh()->amount_requested);
    }
}
