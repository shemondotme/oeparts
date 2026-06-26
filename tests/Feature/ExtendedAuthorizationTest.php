<?php

namespace Tests\Feature;

use App\Enums\ContactStatus;
use App\Enums\PartInquiryStatus;
use App\Enums\SectionStatus;
use App\Filament\Resources\AbandonedCartResource\Pages\ListAbandonedCarts;
use App\Filament\Resources\BlogPostResource\Pages\ListBlogPosts;
use App\Filament\Resources\ContactMessageResource\Pages\ListContactMessages;
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\FaqResource\Pages\ListFaqs;
use App\Filament\Resources\NewsletterCampaignResource\Pages\ListNewsletterCampaigns;
use App\Filament\Resources\PartInquiryResource\Pages\ListPartInquiries;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Filament\Resources\SectionResource\Pages\ListSections;
use App\Filament\Resources\TestimonialResource\Pages\ListTestimonials;
use App\Models\AbandonedCart;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Condition;
use App\Models\ContactMessage;
use App\Models\Faq;
use App\Models\Manufacturer;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\PartInquiry;
use App\Models\Product;
use App\Models\Section;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExtendedAuthorizationTest extends TestCase
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

    // ── Tier 1: actively exploitable today — ProductResource::toggleStock ──

    #[Test]
    public function support_cannot_toggle_product_stock_but_manager_can(): void
    {
        $manufacturer = Manufacturer::create(['name' => json_encode(['en' => 'Test']), 'slug' => 'test-'.uniqid(), 'country_code' => 'DE']);
        $condition = Condition::first() ?? Condition::create(['name' => 'New', 'slug' => 'new', 'bg_color' => '#fff', 'text_color' => '#000']);
        $product = Product::factory()->create([
            'manufacturer_id' => $manufacturer->id,
            'condition_id' => $condition->id,
            'is_in_stock' => true,
        ]);

        $support = $this->adminWithRole('support');
        $this->actingAs($support, 'admin');
        Livewire::test(ListProducts::class)->assertTableActionHidden('toggleStock', $product);
        $this->assertTrue($product->refresh()->is_in_stock);

        $manager = $this->adminWithRole('manager');
        $this->actingAs($manager, 'admin');
        Livewire::test(ListProducts::class)
            ->assertTableActionVisible('toggleStock', $product)
            ->callTableAction('toggleStock', $product);
        $this->assertFalse($product->refresh()->is_in_stock);
    }

    // ── Tier 3: Policy permission-key mismatch fixes ──

    #[Test]
    public function abandoned_cart_policy_now_grants_manager_and_admin_real_access(): void
    {
        $manager = $this->adminWithRole('manager');
        $adminRole = $this->adminWithRole('admin');
        $cart = AbandonedCart::create([
            'guest_email' => 'cart@example.com',
            'cart_snapshot' => [],
            'last_active_at' => now(),
            'recovery_email_sent' => false,
        ]);

        $this->assertTrue($manager->can('viewAny', AbandonedCart::class));
        $this->assertTrue($manager->can('view', $cart));
        $this->assertTrue($manager->can('update', $cart));
        $this->assertTrue($adminRole->can('view', $cart));
        $this->assertTrue($adminRole->can('update', $cart));

        $catalogAdmin = $this->adminWithRole('catalog_admin');
        $this->assertFalse($catalogAdmin->can('view', $cart));
    }

    #[Test]
    public function newsletter_campaign_policy_resolves_to_seeded_newsletters_permissions(): void
    {
        $editor = $this->adminWithPermissions('newsletter_editor_test', ['view newsletters', 'edit newsletters']);
        $viewerOnly = $this->adminWithPermissions('newsletter_viewer_test', ['view newsletters']);
        $campaign = NewsletterCampaign::factory()->create();

        $this->assertTrue($editor->can('viewAny', NewsletterCampaign::class));
        $this->assertTrue($editor->can('update', $campaign));

        $this->assertTrue($viewerOnly->can('view', $campaign));
        $this->assertFalse($viewerOnly->can('update', $campaign));
    }

    #[Test]
    public function newsletter_subscriber_policy_resolves_to_seeded_newsletters_permissions(): void
    {
        $editor = $this->adminWithPermissions('newsletter_sub_editor_test', ['view newsletters', 'edit newsletters']);
        $viewerOnly = $this->adminWithPermissions('newsletter_sub_viewer_test', ['view newsletters']);
        $subscriber = NewsletterSubscriber::factory()->create();

        $this->assertTrue($editor->can('viewAny', NewsletterSubscriber::class));
        $this->assertTrue($editor->can('update', $subscriber));

        $this->assertTrue($viewerOnly->can('view', $subscriber));
        $this->assertFalse($viewerOnly->can('update', $subscriber));
    }

    // ── Tier 2: defense-in-depth — mechanism verified via synthetic roles ──
    // None of these are exploitable by any seeded role today (verified directly
    // against RolesSeeder.php), but each action lacked ->authorize() entirely.
    // A synthetic view-only role proves the gate now genuinely blocks execution.

    #[Test]
    public function customer_actions_require_update_permission(): void
    {
        $user = User::factory()->create();
        $viewOnly = $this->adminWithPermissions('customers_view_only_test', ['view customers']);
        $editor = $this->adminWithRole('manager'); // manager has view+edit customers

        $this->actingAs($viewOnly, 'admin');
        Livewire::test(ListCustomers::class)
            ->assertTableActionHidden('resetPassword', $user)
            ->assertTableActionHidden('toggleActive', $user);

        $this->actingAs($editor, 'admin');
        Livewire::test(ListCustomers::class)
            ->assertTableActionVisible('toggleActive', $user)
            ->callTableAction('toggleActive', $user);
        $this->assertFalse($user->refresh()->is_active);
    }

    #[Test]
    public function contact_message_actions_require_update_permission(): void
    {
        $message = ContactMessage::factory()->create(['status' => ContactStatus::Unread]);
        $viewOnly = $this->adminWithPermissions('contact_view_only_test', ['view contact messages']);
        $editor = $this->adminWithRole('support'); // support has view+edit contact messages

        $this->actingAs($viewOnly, 'admin');
        Livewire::test(ListContactMessages::class)
            ->assertTableActionHidden('markRead', $message)
            ->assertTableActionHidden('markResolved', $message);

        $this->actingAs($editor, 'admin');
        Livewire::test(ListContactMessages::class)
            ->assertTableActionVisible('markRead', $message)
            ->callTableAction('markRead', $message);
        $this->assertSame('read', $message->refresh()->status->value);
    }

    #[Test]
    public function part_inquiry_actions_require_update_permission(): void
    {
        $inquiry = PartInquiry::factory()->create(['status' => PartInquiryStatus::New]);
        $viewOnly = $this->adminWithPermissions('inquiries_view_only_test', ['view inquiries']);
        $editor = $this->adminWithRole('manager');

        $this->actingAs($viewOnly, 'admin');
        Livewire::test(ListPartInquiries::class)->assertTableActionHidden('mark_sourced', $inquiry);

        $this->actingAs($editor, 'admin');
        Livewire::test(ListPartInquiries::class)
            ->assertTableActionVisible('mark_sourced', $inquiry)
            ->callTableAction('mark_sourced', $inquiry);
        $this->assertSame(PartInquiryStatus::Sourced, $inquiry->refresh()->status);
    }

    #[Test]
    public function blog_post_toggle_publish_requires_update_permission(): void
    {
        $post = BlogPost::factory()->create();
        $viewOnly = $this->adminWithPermissions('blog_view_only_test', ['view blog']);
        $editor = $this->adminWithPermissions('blog_editor_test', ['view blog', 'edit blog']);

        $this->actingAs($viewOnly, 'admin');
        Livewire::test(ListBlogPosts::class)->assertTableActionHidden('togglePublish', $post);

        $this->actingAs($editor, 'admin');
        Livewire::test(ListBlogPosts::class)->assertTableActionVisible('togglePublish', $post);
    }

    #[Test]
    public function faq_toggle_active_requires_update_permission(): void
    {
        $faq = Faq::factory()->create();
        $viewOnly = $this->adminWithPermissions('faqs_view_only_test', ['view faqs']);
        $editor = $this->adminWithPermissions('faqs_editor_test', ['view faqs', 'edit faqs']);

        $this->actingAs($viewOnly, 'admin');
        Livewire::test(ListFaqs::class)->assertTableActionHidden('toggleActive', $faq);

        $this->actingAs($editor, 'admin');
        Livewire::test(ListFaqs::class)
            ->assertTableActionVisible('toggleActive', $faq)
            ->callTableAction('toggleActive', $faq);
        $this->assertNotNull($faq->refresh());
    }

    #[Test]
    public function testimonial_toggle_active_requires_update_permission(): void
    {
        $testimonial = Testimonial::factory()->create();
        $viewOnly = $this->adminWithPermissions('testimonials_view_only_test', ['view testimonials']);
        $editor = $this->adminWithPermissions('testimonials_editor_test', ['view testimonials', 'edit testimonials']);

        $this->actingAs($viewOnly, 'admin');
        Livewire::test(ListTestimonials::class)->assertTableActionHidden('toggleActive', $testimonial);

        $this->actingAs($editor, 'admin');
        Livewire::test(ListTestimonials::class)->assertTableActionVisible('toggleActive', $testimonial);
    }

    #[Test]
    public function section_publish_and_archive_require_update_permission(): void
    {
        $section = Section::factory()->create(['status' => SectionStatus::Draft]);
        $viewOnly = $this->adminWithPermissions('sections_view_only_test', ['view sections']);
        $editor = $this->adminWithPermissions('sections_editor_test', ['view sections', 'edit sections']);

        $this->actingAs($viewOnly, 'admin');
        Livewire::test(ListSections::class)
            ->assertTableActionHidden('publish', $section)
            ->assertTableActionHidden('archive', $section);

        $this->actingAs($editor, 'admin');
        Livewire::test(ListSections::class)->assertTableActionVisible('publish', $section);
    }

    #[Test]
    public function newsletter_campaign_actions_require_update_permission(): void
    {
        $campaign = NewsletterCampaign::factory()->create();
        $viewOnly = $this->adminWithPermissions('newsletters_view_only_test2', ['view newsletters']);
        $editor = $this->adminWithPermissions('newsletters_editor_test2', ['view newsletters', 'edit newsletters']);

        $this->actingAs($viewOnly, 'admin');
        Livewire::test(ListNewsletterCampaigns::class)
            ->assertTableActionHidden('send', $campaign)
            ->assertTableActionHidden('duplicateCampaign', $campaign);

        $this->actingAs($editor, 'admin');
        Livewire::test(ListNewsletterCampaigns::class)->assertTableActionVisible('send', $campaign);
    }

    #[Test]
    public function abandoned_cart_send_recovery_requires_update_permission(): void
    {
        // This resource has only ever had one permission tier ('view abandoned
        // carts'), now also gating update/delete per the AbandonedCartPolicy
        // fix above — so a role with zero abandoned-cart permission can't even
        // reach the list page (page-level viewAny denial), and any role that
        // CAN reach it automatically has the sendRecovery action available too.
        $cart = AbandonedCart::create([
            'guest_email' => 'recover@example.com',
            'cart_snapshot' => [],
            'last_active_at' => now(),
            'recovery_email_sent' => false,
        ]);
        $catalogAdmin = $this->adminWithRole('catalog_admin'); // no abandoned-cart permission at all
        $manager = $this->adminWithRole('manager'); // has 'view abandoned carts'

        $this->actingAs($catalogAdmin, 'admin');
        $this->get(\App\Filament\Resources\AbandonedCartResource::getUrl('index'))->assertForbidden();

        $this->actingAs($manager, 'admin');
        Livewire::test(ListAbandonedCarts::class)->assertTableActionVisible('sendRecovery', $cart);
    }

    // ── Option S: ConditionResource had zero authorization (no Policy file,
    // no seeded permissions, no AuthServiceProvider registration). Filament's
    // verified default with no policy registered is Response::allow() — so
    // the real regression test is that a role WITHOUT condition permissions
    // is now correctly DENIED, not that catalog_admin (who always could,
    // before and after) can still manage conditions.

    #[Test]
    public function condition_policy_now_denies_roles_without_permission(): void
    {
        $condition = Condition::create([
            'name' => 'Test Condition',
            'slug' => 'test-condition-'.uniqid(),
            'bg_color' => '#fff',
            'text_color' => '#000',
        ]);

        $support = $this->adminWithRole('support'); // zero condition permissions, before and after
        $this->assertFalse($support->can('viewAny', Condition::class));
        $this->assertFalse($support->can('view', $condition));
        $this->assertFalse($support->can('update', $condition));
        $this->assertFalse($support->can('delete', $condition));
    }

    #[Test]
    public function condition_policy_grants_catalog_admin_full_crud(): void
    {
        $condition = Condition::create([
            'name' => 'Test Condition 2',
            'slug' => 'test-condition-2-'.uniqid(),
            'bg_color' => '#fff',
            'text_color' => '#000',
        ]);

        $catalogAdmin = $this->adminWithRole('catalog_admin');
        $this->assertTrue($catalogAdmin->can('viewAny', Condition::class));
        $this->assertTrue($catalogAdmin->can('create', Condition::class));
        $this->assertTrue($catalogAdmin->can('update', $condition));
        $this->assertTrue($catalogAdmin->can('delete', $condition));
    }

    #[Test]
    public function condition_policy_grants_manager_and_admin_edit_but_not_delete(): void
    {
        $condition = Condition::create([
            'name' => 'Test Condition 3',
            'slug' => 'test-condition-3-'.uniqid(),
            'bg_color' => '#fff',
            'text_color' => '#000',
        ]);

        foreach (['manager', 'admin'] as $role) {
            $roleAdmin = $this->adminWithRole($role);
            $this->assertTrue($roleAdmin->can('viewAny', Condition::class), "{$role} should be able to view conditions");
            $this->assertTrue($roleAdmin->can('update', $condition), "{$role} should be able to update conditions");
            $this->assertFalse($roleAdmin->can('delete', $condition), "{$role} should NOT be able to delete conditions");
        }
    }

    // ── Option T: catalog_admin was missing category permissions despite
    // CategoryResource living in the Catalog nav group (a missing grant, not
    // a Policy bug — CategoryPolicy was already correctly shaped).

    #[Test]
    public function catalog_admin_now_has_full_category_crud(): void
    {
        $category = Category::factory()->create();
        $catalogAdmin = $this->adminWithRole('catalog_admin');

        $this->assertTrue($catalogAdmin->can('viewAny', Category::class));
        $this->assertTrue($catalogAdmin->can('create', Category::class));
        $this->assertTrue($catalogAdmin->can('update', $category));
        $this->assertTrue($catalogAdmin->can('delete', $category));
    }

    // ── Customers module deep gap analysis (Option V): 4 bulk actions
    // (PartInquiry's bulkMarkSourced/bulkMarkUnavailable, ContactMessage's
    // bulkMarkRead/bulkMarkResolved) plus CustomerResource::sendEmail had zero
    // ->authorize(). None were exploitable by any seeded role (every role with
    // 'view inquiries'/'view contact messages' also has the matching 'edit'
    // permission), so these are Tier 2 defense-in-depth, same as the row-action
    // tests above — a synthetic view-only role proves the gate now blocks them.

    #[Test]
    public function part_inquiry_bulk_actions_require_update_permission(): void
    {
        $inquiry = PartInquiry::factory()->create(['status' => PartInquiryStatus::New]);
        $viewOnly = $this->adminWithPermissions('inquiries_bulk_view_only_test', ['view inquiries']);
        $editor = $this->adminWithRole('manager');

        $this->actingAs($viewOnly, 'admin');
        Livewire::test(ListPartInquiries::class)->assertTableBulkActionHidden('bulkMarkUnavailable');

        $this->actingAs($editor, 'admin');
        Livewire::test(ListPartInquiries::class)
            ->assertTableBulkActionVisible('bulkMarkUnavailable')
            ->callTableBulkAction('bulkMarkUnavailable', [$inquiry]);
        $this->assertSame(PartInquiryStatus::Unavailable, $inquiry->refresh()->status);
    }

    #[Test]
    public function contact_message_bulk_actions_require_update_permission(): void
    {
        $message = ContactMessage::factory()->create(['status' => ContactStatus::Unread]);
        $viewOnly = $this->adminWithPermissions('contact_bulk_view_only_test', ['view contact messages']);
        $editor = $this->adminWithRole('support');

        $this->actingAs($viewOnly, 'admin');
        Livewire::test(ListContactMessages::class)->assertTableBulkActionHidden('bulkMarkResolved');

        $this->actingAs($editor, 'admin');
        Livewire::test(ListContactMessages::class)
            ->assertTableBulkActionVisible('bulkMarkResolved')
            ->callTableBulkAction('bulkMarkResolved', [$message]);
        $this->assertSame('resolved', $message->refresh()->status->value);
    }

    #[Test]
    public function customer_send_email_action_requires_update_permission(): void
    {
        $user = User::factory()->create();
        $viewOnly = $this->adminWithPermissions('customers_send_email_view_only_test', ['view customers']);
        $editor = $this->adminWithRole('manager');

        $this->actingAs($viewOnly, 'admin');
        Livewire::test(ListCustomers::class)->assertTableActionHidden('sendEmail', $user);

        $this->actingAs($editor, 'admin');
        Livewire::test(ListCustomers::class)->assertTableActionVisible('sendEmail', $user);
    }

    // ── Customers module deep gap analysis (Option V): admin/manager could not
    // manage Contact Messages at all (only support could), despite both roles
    // already holding full customer/inquiry CRUD in the same nav group.

    #[Test]
    public function manager_and_admin_now_have_contact_message_permissions(): void
    {
        $message = ContactMessage::factory()->create();

        foreach (['manager', 'admin'] as $role) {
            $roleAdmin = $this->adminWithRole($role);
            $this->assertTrue($roleAdmin->can('viewAny', ContactMessage::class), "{$role} should be able to view contact messages");
            $this->assertTrue($roleAdmin->can('update', $message), "{$role} should be able to update contact messages");
        }
    }
}
