<?php

namespace Tests\Feature;

use App\Enums\DiscountType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductCondition;
use App\Enums\RedirectType;
use App\Enums\RefundStatus;
use App\Models\Admin;
use App\Models\CarModel;
use App\Models\Coupon;
use App\Models\IpBlocklist;
use App\Models\Manufacturer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Redirect;
use App\Models\RefundRequest;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminCoreModulesTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Core Admin',
            'email' => 'core-admin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }

    #[Test]
    public function bulk_update_console_renders_blueprint_workflow(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.catalog.bulk-update.index'));

        $response->assertOk();
        $response->assertSee('Bulk update workspace');
        $response->assertSee('Preview Changes');
        $response->assertSee('CONFIRM');
    }

    #[Test]
    public function core_admin_get_routes_render_without_server_errors(): void
    {
        $fixtures = $this->coreFixtures();

        $routes = [
            ['admin.orders.index'],
            ['admin.orders.show', $fixtures['order']],
            ['admin.orders.packing-slip', $fixtures['order']],
            ['admin.refunds.index'],
            ['admin.refunds.show', $fixtures['refund']],
            ['admin.catalog.products.index'],
            ['admin.catalog.products.create'],
            ['admin.catalog.products.import'],
            ['admin.catalog.products.show', $fixtures['product']],
            ['admin.catalog.products.edit', $fixtures['product']],
            ['admin.catalog.manufacturers.index'],
            ['admin.catalog.manufacturers.create'],
            ['admin.catalog.manufacturers.show', $fixtures['manufacturer']],
            ['admin.catalog.manufacturers.edit', $fixtures['manufacturer']],
            ['admin.catalog.car-models.index'],
            ['admin.catalog.car-models.create'],
            ['admin.catalog.car-models.show', $fixtures['carModel']],
            ['admin.catalog.car-models.edit', $fixtures['carModel']],
            ['admin.settings.index'],
            ['admin.settings.create'],
            ['admin.settings.edit', 'general'],
            ['admin.settings.preloader'],
            ['admin.settings.ip-blocklist.index'],
            ['admin.settings.ip-blocklist.create'],
            ['admin.settings.redirects.index'],
            ['admin.settings.redirects.create'],
            ['admin.settings.redirects.edit', $fixtures['redirect']],
            ['admin.coupons.index'],
            ['admin.coupons.create'],
            ['admin.coupons.show', $fixtures['coupon']],
            ['admin.coupons.edit', $fixtures['coupon']],
            ['admin.customers.index'],
            ['admin.customers.show', $fixtures['user']],
        ];

        foreach ($routes as $route) {
            $routeName = $route[0];
            $parameter = $route[1] ?? [];

            $this->actingAs($this->admin, 'admin')
                ->get(route($routeName, $parameter))
                ->assertOk();
        }
    }

    private function coreFixtures(): array
    {
        $user = User::create([
            'name' => 'Core Customer',
            'email' => 'core-customer@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $manufacturer = Manufacturer::create([
            'name' => ['en' => 'Bosch'],
            'slug' => 'bosch',
            'country_code' => 'DE',
            'is_active' => true,
            'is_verified_oem' => true,
            'sort_order' => 1,
        ]);

        $carModel = CarModel::create([
            'manufacturer_id' => $manufacturer->id,
            'name' => 'Sprinter',
            'slug' => 'sprinter',
            'year_from' => 2018,
            'year_to' => 2026,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $product = Product::create([
            'manufacturer_id' => $manufacturer->id,
            'oem_number' => 'A000000001',
            'normalized_oem' => 'A000000001',
            'name' => ['en' => 'Brake Pad'],
            'description' => ['en' => 'Factory brake pad'],
            'condition' => ProductCondition::New,
            'price' => '99.00',
            'delivery_time' => '2-3 days',
            'moq' => 1,
            'is_in_stock' => true,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-CORE-001',
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'payment_method' => PaymentMethod::Card,
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => '100.00',
            'discount_amount' => '0.00',
            'shipping_cost' => '10.00',
            'vat_amount' => '21.00',
            'grand_total' => '131.00',
            'shipping_name' => 'Core Customer',
            'shipping_address_line1' => '1 Test Street',
            'shipping_city' => 'Berlin',
            'shipping_postal_code' => '10115',
            'shipping_country_code' => 'DE',
            'ip_address' => '127.0.0.1',
        ]);

        $refund = RefundRequest::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'reason' => 'Wrong part',
            'amount_requested' => '50.00',
            'status' => RefundStatus::Pending,
        ]);

        $coupon = Coupon::create([
            'code' => 'CORE10',
            'name' => 'Core Discount',
            'discount_type' => DiscountType::Percentage,
            'discount_value' => '10.00',
            'min_order_amount' => '0.00',
            'usage_limit' => 100,
            'usage_limit_per_user' => 1,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $redirect = Redirect::create([
            'from_url' => '/old-core',
            'to_url' => '/new-core',
            'type' => RedirectType::Permanent,
            'is_active' => true,
            'hit_count' => 0,
        ]);

        IpBlocklist::create([
            'ip_address' => '203.0.113.10',
            'reason' => 'Smoke test',
            'blocked_by' => $this->admin->id,
            'is_active' => true,
        ]);

        Setting::create([
            'group' => 'general',
            'key' => 'site_name',
            'value' => 'OEMHub',
            'type' => 'string',
            'is_encrypted' => false,
        ]);

        return compact('user', 'manufacturer', 'carModel', 'product', 'order', 'refund', 'coupon', 'redirect');
    }
}
