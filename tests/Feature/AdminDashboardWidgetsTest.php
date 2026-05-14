<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminDashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Dashboard Admin',
            'email' => 'dashboard-admin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }

    #[Test]
    public function dashboard_renders_real_widget_pipeline_data(): void
    {
        $customer = User::create([
            'name' => 'Workshop Buyer',
            'email' => 'buyer@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        Order::create([
            'order_number' => 'ORD-WIDGET-001',
            'user_id' => $customer->id,
            'status' => OrderStatus::Paid,
            'payment_method' => PaymentMethod::Card,
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => '120.00',
            'discount_amount' => '0.00',
            'shipping_cost' => '10.00',
            'vat_amount' => '20.00',
            'grand_total' => '150.00',
            'shipping_name' => 'Workshop Buyer',
            'shipping_address_line1' => '12 Test Street',
            'shipping_city' => 'Berlin',
            'shipping_postal_code' => '10115',
            'shipping_country_code' => 'DE',
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Total Orders');
        $response->assertSee('Total Revenue');
        $response->assertSee('150.00');
        $response->assertSee('ORD-WIDGET-001');
        $response->assertSee('Sales Last 30 Days');
        $response->assertDontSee('42% Used');
    }

    #[Test]
    public function dashboard_preferences_reject_unknown_widget_ids(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.dashboard.preferences.update'), [
                'preferences' => [
                    ['id' => 'not_a_real_widget', 'visible' => true, 'col_span' => 1, 'row_span' => 1],
                ],
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('preferences.0.id');
    }
}
