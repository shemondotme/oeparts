<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminAuthTest extends TestCase
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
    }

    #[Test]
    public function admin_can_view_login_page(): void
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
        $response->assertViewIs('admin.auth.login');
        $response->assertSee('Sign in to your account');
    }

    #[Test]
    public function admin_can_login_with_valid_credentials(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated('admin');
    }

    #[Test]
    public function admin_login_fails_with_invalid_password(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
        $this->assertGuest('admin');
    }

    #[Test]
    public function inactive_admin_cannot_login(): void
    {
        $this->admin->update(['is_active' => false]);

        $response = $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
        $this->assertGuest('admin');
    }

    #[Test]
    public function admin_can_logout(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->post('/admin/logout');

        $response->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    #[Test]
    public function authenticated_admin_can_access_dashboard(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get('/admin/dashboard');

        $response->assertStatus(200);
        $response->assertViewIs('admin.dashboard.index');
        $response->assertSee('Dashboard');
    }

    #[Test]
    public function guest_cannot_access_admin_dashboard(): void
    {
        $response = $this->get('/admin/dashboard');

        $response->assertRedirect(route('admin.login'));
    }

    #[Test]
    public function customer_cannot_access_admin_dashboard(): void
    {
        // Create a regular user (customer)
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user, 'web');

        $response = $this->get('/admin/dashboard');

        $response->assertRedirect(route('admin.login'));
    }

    #[Test]
    public function admin_guard_isolation_customer_cannot_impersonate_admin(): void
    {
        // Customer authenticated on web guard
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user, 'web');

        // Should not be authenticated on admin guard
        $this->assertGuest('admin');

        // Attempt to access admin route
        $response = $this->get('/admin/dashboard');
        $response->assertRedirect(route('admin.login'));
    }

    #[Test]
    public function admin_guard_isolation_admin_cannot_impersonate_customer(): void
    {
        $this->actingAs($this->admin, 'admin');

        // Should not be authenticated on web guard
        $this->assertGuest('web');

        // Attempt to access customer account route (should redirect to login)
        $response = $this->get('/en/account/dashboard');
        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function dashboard_preferences_can_be_updated(): void
    {
        $this->actingAs($this->admin, 'admin');

        $preferences = [
            ['id' => 'total_orders', 'visible' => true],
            ['id' => 'total_revenue', 'visible' => false],
        ];

        $response = $this->postJson('/admin/dashboard/preferences', [
            'preferences' => $preferences,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->admin->refresh();
        $this->assertEquals($preferences, $this->admin->dashboard_preferences);
    }
}