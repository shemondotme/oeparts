<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RefundRequestThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([\Database\Seeders\SettingsSeeder::class]);
    }

    #[Test]
    public function submitting_refund_requests_beyond_the_limit_is_throttled(): void
    {
        $user = User::factory()->create();

        // orders.refund_request_rate_limit defaults to 5/hour — the 6th attempt
        // (even against a fresh order each time, since the limiter is per-user/IP,
        // not per-order) should be throttled.
        for ($i = 0; $i < 5; $i++) {
            $order = Order::factory()->create([
                'user_id' => $user->id,
                'status' => OrderStatus::Delivered,
                'updated_at' => now()->subDay(),
            ]);

            $response = $this->actingAs($user, 'web')->post(
                route('frontend.account.order.refund.submit', ['lang' => 'en', 'order' => $order]),
                ['reason' => str_repeat('a', 25)]
            );

            $this->assertNotEquals(429, $response->getStatusCode());
        }

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Delivered,
            'updated_at' => now()->subDay(),
        ]);

        $this->actingAs($user, 'web')
            ->post(route('frontend.account.order.refund.submit', ['lang' => 'en', 'order' => $order]), [
                'reason' => str_repeat('a', 25),
            ])
            ->assertStatus(429);
    }
}
