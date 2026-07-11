<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Every settings knob must actually change behavior — these lock the
 * connections the Settings audit found broken or missing.
 */
class SettingsTruthTest extends TestCase
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
    }

    private function setSetting(string $group, string $key, string $value): void
    {
        Setting::updateOrCreate(['group' => $group, 'key' => $key], ['value' => $value, 'type' => 'integer']);
        Cache::forget("settings.{$group}");
    }

    private function shipHistory(Order $order, \Carbon\CarbonInterface $shippedAt): void
    {
        $history = $order->statusHistory()->create([
            'old_status' => OrderStatus::Processing->value,
            'new_status' => OrderStatus::Shipped->value,
        ]);

        // created_at isn't mass-assignable — set the ship time directly.
        $history->newQuery()->whereKey($history->getKey())->update(['created_at' => $shippedAt]);
    }

    public function test_refund_window_reads_the_orders_group_the_page_edits(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Delivered,
            'updated_at' => now()->subDays(10),
        ]);

        // 10 days old: within a 14-day window, outside a 7-day one — and the
        // knob is orders.*, the group OrdersSettings actually manages.
        $this->setSetting('orders', 'refund_window_days', '7');
        $this->actingAs($user, 'web')
            ->get(route('frontend.account.order.refund.form', ['lang' => 'en', 'order' => $order]))
            ->assertRedirect();

        $this->setSetting('orders', 'refund_window_days', '14');
        $this->actingAs($user, 'web')
            ->get(route('frontend.account.order.refund.form', ['lang' => 'en', 'order' => $order]))
            ->assertOk();
    }

    public function test_customer_cancel_window_is_enforced(): void
    {
        $user = User::factory()->create();
        $old = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Pending,
            'created_at' => now()->subHours(48),
        ]);

        $this->setSetting('orders', 'customer_cancel_window_hours', '24');
        $this->actingAs($user, 'web')
            ->post(route('frontend.account.order.cancel', ['lang' => 'en', 'order' => $old]))
            ->assertSessionHas('error');
        $this->assertSame(OrderStatus::Pending, $old->fresh()->status, 'outside the window the order must stay');

        $this->setSetting('orders', 'customer_cancel_window_hours', '72');
        $this->actingAs($user, 'web')
            ->post(route('frontend.account.order.cancel', ['lang' => 'en', 'order' => $old]))
            ->assertSessionHas('success');
        $this->assertSame(OrderStatus::Cancelled, $old->fresh()->status);
    }

    public function test_cancel_window_zero_means_status_only(): void
    {
        $user = User::factory()->create();
        $ancient = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Pending,
            'created_at' => now()->subDays(30),
        ]);

        $this->setSetting('orders', 'customer_cancel_window_hours', '0');
        $this->actingAs($user, 'web')
            ->post(route('frontend.account.order.cancel', ['lang' => 'en', 'order' => $ancient]))
            ->assertSessionHas('success');
    }

    public function test_auto_complete_marks_only_due_shipped_orders(): void
    {
        $this->setSetting('orders', 'auto_complete_days', '14');

        $dueShipped = Order::factory()->create(['status' => OrderStatus::Shipped]);
        $this->shipHistory($dueShipped, now()->subDays(20));

        $freshShipped = Order::factory()->create(['status' => OrderStatus::Shipped]);
        $this->shipHistory($freshShipped, now()->subDays(2));

        $this->artisan('oeparts:orders:auto-complete')->assertSuccessful();

        $this->assertSame(OrderStatus::Delivered, $dueShipped->fresh()->status);
        $this->assertSame(OrderStatus::Shipped, $freshShipped->fresh()->status);
    }

    public function test_auto_complete_zero_disables(): void
    {
        $this->setSetting('orders', 'auto_complete_days', '0');

        $due = Order::factory()->create(['status' => OrderStatus::Shipped]);
        $this->shipHistory($due, now()->subDays(60));

        $this->artisan('oeparts:orders:auto-complete')->assertSuccessful();

        $this->assertSame(OrderStatus::Shipped, $due->fresh()->status);
    }
}
