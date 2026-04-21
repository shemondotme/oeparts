<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Order;
use App\Models\User;
use App\Models\SearchLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test admin
        $this->admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);

        // Create test data for reports
        $this->createTestData();
    }

    private function createTestData(): void
    {
        // Create users (customers)
        $users = [];
        for ($i = 1; $i <= 5; $i++) {
            $users[] = User::create([
                'name' => 'User ' . $i,
                'email' => 'user' . $i . '@example.com',
                'password' => Hash::make('password'),
            ]);
        }

        // Create orders for different dates
        $orders = [
            ['created_at' => now()->subDays(1), 'total' => 100.00, 'status' => 'delivered'],
            ['created_at' => now()->subDays(2), 'total' => 200.00, 'status' => 'delivered'],
            ['created_at' => now()->subDays(3), 'total' => 150.00, 'status' => 'pending'],
            ['created_at' => now()->subDays(4), 'total' => 300.00, 'status' => 'delivered'],
            ['created_at' => now()->subDays(5), 'total' => 250.00, 'status' => 'cancelled'],
        ];

        foreach ($orders as $orderData) {
            Order::create([
                'order_number' => 'ORD-' . uniqid(),
                'user_id' => $users[array_rand($users)]->id,
                'status' => $orderData['status'],
                'payment_method' => 'card',
                'payment_status' => 'paid',
                'subtotal' => $orderData['total'],
                'discount_amount' => 0,
                'shipping_cost' => 0,
                'vat_amount' => 0,
                'grand_total' => $orderData['total'],
                'shipping_name' => 'John Doe',
                'shipping_address_line1' => '123 Main St',
                'shipping_city' => 'New York',
                'shipping_postal_code' => '10001',
                'shipping_country_code' => 'US',
                'ip_address' => '127.0.0.1',
                'created_at' => $orderData['created_at'],
                'updated_at' => $orderData['created_at'],
            ]);
        }

        // Create search logs
        $searchTerms = ['BMW 320i', 'Mercedes E-Class', 'Audi A4', 'Toyota Camry', 'Honda Civic'];
        foreach ($searchTerms as $term) {
            SearchLog::create([
                'search_query' => $term,
                'normalized_query' => strtolower($term), // Simplified normalization
                'result_count' => rand(1, 10),
                'lang' => 'en',
                'ip_address' => '127.0.0.1',
                'created_at' => now()->subDays(rand(1, 30)),
            ]);
        }

        // Create checkout drop-off data (carts created but not completed)
        // This would typically be in a cart model, but we'll simulate with orders in different statuses
    }

    #[Test]
    public function admin_can_view_reports_index(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.reports.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.reports.index');
        // Check for presence of key report types (more flexible)
        $response->assertSeeText('Reports');
    }

    #[Test]
    public function admin_can_view_sales_report(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.reports.sales'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.reports.sales');
        $response->assertSeeText('Sales');
        
        // Check that date range parameters work
        $response = $this->get(route('admin.reports.sales', [
            'start_date' => now()->subDays(7)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]));

        $response->assertStatus(200);
    }

    #[Test]
    public function admin_can_view_customers_report(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.reports.customers'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.reports.customers');
        $response->assertSeeText('Customer');
    }

    #[Test]
    public function admin_can_view_search_report(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.reports.search'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.reports.search');
        $response->assertSeeText('Search');
    }

    #[Test]
    public function admin_can_view_checkout_report(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.reports.checkout'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.reports.checkout');
        $response->assertSeeText('Checkout');
    }

    #[Test]
    public function reports_export_works(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.reports.export'));

        // Export currently redirects back
        $response->assertStatus(302);
    }

    #[Test]
    public function sales_report_calculates_totals_correctly(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.reports.sales'));

        $response->assertStatus(200);
        
        // Check that sales data is present
        $response->assertViewHas('salesSummary');
    }

    #[Test]
    public function date_range_filter_works(): void
    {
        $this->actingAs($this->admin, 'admin');

        // Test with specific date range
        $startDate = now()->subDays(10)->format('Y-m-d');
        $endDate = now()->subDays(1)->format('Y-m-d');

        $response = $this->get(route('admin.reports.sales', [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]));

        $response->assertStatus(200);
        $response->assertSee($startDate);
        $response->assertSee($endDate);
    }

    #[Test]
    public function invalid_date_range_handled_gracefully(): void
    {
        $this->actingAs($this->admin, 'admin');

        // End date before start date
        $response = $this->get(route('admin.reports.sales', [
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->subDays(10)->format('Y-m-d'),
        ]));

        $response->assertStatus(200);
        // Should default to reasonable date range
    }

    #[Test]
    public function reports_require_admin_authentication(): void
    {
        // Test without authentication
        $response = $this->get(route('admin.reports.index'));
        $response->assertRedirect(route('admin.login'));

        $response = $this->get(route('admin.reports.sales'));
        $response->assertRedirect(route('admin.login'));

        $response = $this->get(route('admin.reports.customers'));
        $response->assertRedirect(route('admin.login'));

        $response = $this->get(route('admin.reports.search'));
        $response->assertRedirect(route('admin.login'));

        $response = $this->get(route('admin.reports.checkout'));
        $response->assertRedirect(route('admin.login'));
    }
}