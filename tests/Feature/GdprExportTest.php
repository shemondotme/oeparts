<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ContactMessage;
use App\Models\LoginLog;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\PartInquiry;
use App\Models\Product;
use App\Models\RefundRequest;
use App\Models\SearchLog;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\GdprExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GdprExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([\Database\Seeders\RolesSeeder::class]);
    }

    private function adminWithRole(string $role): Admin
    {
        $admin = Admin::factory()->create();
        $admin->assignRole($role);

        return $admin;
    }

    private function adminWithPermissions(string $roleName, array $permissions): Admin
    {
        $role = Role::create(['name' => $roleName, 'guard_name' => 'admin']);
        $role->givePermissionTo($permissions);

        return $this->adminWithRole($roleName);
    }

    #[Test]
    public function export_for_user_includes_every_personal_data_table(): void
    {
        $user = User::factory()->create(['email' => 'gdpr-subject@example.test']);

        UserAddress::create([
            'user_id' => $user->id,
            'label' => 'Home',
            'first_name' => 'Test',
            'last_name' => 'Subject',
            'address_line1' => '1 Test St',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'country_code' => 'DE',
            'is_default' => true,
        ]);

        $order = Order::factory()->create(['user_id' => $user->id]);
        RefundRequest::factory()->create(['order_id' => $order->id, 'user_id' => $user->id]);

        $manufacturer = Manufacturer::create(['name' => json_encode(['en' => 'Test']), 'slug' => 'test-mfr-'.uniqid(), 'country_code' => 'DE']);
        $condition = Condition::first() ?? Condition::create(['name' => 'New', 'slug' => 'new', 'bg_color' => '#fff', 'text_color' => '#000']);
        $product = Product::factory()->create(['manufacturer_id' => $manufacturer->id, 'condition_id' => $condition->id]);

        $cart = Cart::create(['user_id' => $user->id, 'expires_at' => now()->addDays(7)]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price_at_add' => '10.00',
        ]);

        SearchLog::factory()->create(['user_id' => $user->id]);

        LoginLog::create([
            'user_id' => $user->id,
            'user_type' => 'customer',
            'email' => $user->email,
            'status' => 'success',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        ContactMessage::factory()->create(['email' => $user->email]);
        NewsletterSubscriber::factory()->create(['email' => $user->email]);
        PartInquiry::factory()->create(['email' => $user->email]);

        $data = app(GdprExportService::class)->exportForUser($user);

        $this->assertSame($user->email, $data['profile']['email']);
        $this->assertCount(1, $data['addresses']);
        $this->assertCount(1, $data['orders']);
        $this->assertCount(1, $data['carts']);
        $this->assertCount(1, $data['carts'][0]['items']);
        $this->assertCount(1, $data['refund_requests']);
        $this->assertCount(1, $data['search_logs']);
        $this->assertCount(1, $data['login_logs']);
        $this->assertCount(1, $data['contact_messages']);
        $this->assertNotNull($data['newsletter_subscription']);
        $this->assertCount(1, $data['part_inquiries']);
    }

    #[Test]
    public function export_for_user_with_no_related_data_returns_empty_collections_not_errors(): void
    {
        $user = User::factory()->create();

        $data = app(GdprExportService::class)->exportForUser($user);

        $this->assertSame([], $data['addresses']);
        $this->assertSame([], $data['orders']);
        $this->assertNull($data['newsletter_subscription']);
    }

    #[Test]
    public function export_action_visible_for_update_permission_hidden_otherwise(): void
    {
        $user = User::factory()->create();
        $editor = $this->adminWithRole('manager');
        $viewOnly = $this->adminWithPermissions('customers_export_view_only_test', ['view customers']);

        $this->actingAs($editor, 'admin');
        Livewire::test(ListCustomers::class)->assertTableActionVisible('exportGdprData', $user);

        $this->actingAs($viewOnly, 'admin');
        Livewire::test(ListCustomers::class)->assertTableActionHidden('exportGdprData', $user);
    }

    #[Test]
    public function calling_export_action_logs_an_activity_entry(): void
    {
        $user = User::factory()->create();
        $editor = $this->adminWithRole('manager');

        $this->actingAs($editor, 'admin');
        Livewire::test(ListCustomers::class)->callTableAction('exportGdprData', $user);

        $this->assertDatabaseHas('activity_logs', [
            'admin_id' => $editor->id,
            'action' => 'gdpr_export',
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);
    }
}
