<?php

namespace Tests\Feature;

use App\Enums\ContactStatus;
use App\Enums\PartInquiryStatus;
use App\Enums\SectionStatus;
use App\Filament\Resources\AbandonedCartResource\Pages\ListAbandonedCarts;
use App\Filament\Resources\AdminResource\Pages\ListAdmins;
use App\Filament\Resources\BlogPostResource\Pages\ListBlogPosts;
use App\Filament\Resources\CarModelResource\Pages\ListCarModels;
use App\Filament\Resources\CarrierResource\Pages\ListCarriers;
use App\Filament\Resources\ContactMessageResource\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessageResource\Pages\ViewContactMessage;
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\FaqResource\Pages\ListFaqs;
use App\Filament\Resources\ManufacturerResource\Pages\ListManufacturers;
use App\Filament\Resources\NewsletterCampaignResource\Pages\ListNewsletterCampaigns;
use App\Filament\Resources\NewsletterSubscriberResource\Pages\ListNewsletterSubscribers;
use App\Filament\Resources\PartInquiryResource\Pages\ListPartInquiries;
use App\Filament\Resources\PartInquiryResource\Pages\ViewPartInquiry;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Filament\Resources\SectionResource\Pages\ListSections;
use App\Filament\Resources\TestimonialResource\Pages\ListTestimonials;
use App\Models\AbandonedCart;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\CarModel;
use App\Models\Carrier;
use App\Models\Category;
use App\Models\Condition;
use App\Models\ContactMessage;
use App\Models\EmailLog;
use App\Models\Faq;
use App\Models\FailedSearchLog;
use App\Models\LanguageString;
use App\Models\Manufacturer;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\PartInquiry;
use App\Models\Product;
use App\Models\SearchLog;
use App\Models\Section;
use App\Models\SeoMeta;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
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

    // ── Regression test: ViewContactMessage page duplicates markResolved
    // without the table-level action's ->authorize('update') — found during
    // the Phase 6 security audit (Option LL). ──

    #[Test]
    public function contact_message_view_page_mark_resolved_requires_update_permission(): void
    {
        $message = ContactMessage::factory()->create(['status' => ContactStatus::Unread]);
        $viewOnly = $this->adminWithPermissions('contact_view_page_test', ['view contact messages']);
        $editor = $this->adminWithRole('support');

        $this->actingAs($viewOnly, 'admin');
        Livewire::test(ViewContactMessage::class, ['record' => $message->getRouteKey()])
            ->assertActionHidden('markResolved');
        $this->assertSame(ContactStatus::Unread, $message->refresh()->status);

        $this->actingAs($editor, 'admin');
        Livewire::test(ViewContactMessage::class, ['record' => $message->getRouteKey()])
            ->assertActionVisible('markResolved')
            ->callAction('markResolved');
        $this->assertSame(ContactStatus::Resolved, $message->refresh()->status);
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

    // ── Regression test: ViewPartInquiry page duplicates mark_sourced
    // without the table-level action's ->authorize('update') — found during
    // the Phase 6 security audit (Option LL). ──

    #[Test]
    public function part_inquiry_view_page_mark_sourced_requires_update_permission(): void
    {
        $inquiry = PartInquiry::factory()->create(['status' => PartInquiryStatus::New]);
        $viewOnly = $this->adminWithPermissions('inquiries_view_page_test', ['view inquiries']);
        $editor = $this->adminWithRole('manager');

        $this->actingAs($viewOnly, 'admin');
        Livewire::test(ViewPartInquiry::class, ['record' => $inquiry->getRouteKey()])
            ->assertActionHidden('mark_sourced');
        $this->assertSame(PartInquiryStatus::New, $inquiry->refresh()->status);

        $this->actingAs($editor, 'admin');
        Livewire::test(ViewPartInquiry::class, ['record' => $inquiry->getRouteKey()])
            ->assertActionVisible('mark_sourced')
            ->callAction('mark_sourced');
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

    // ── Phase 6 security audit (Option MM): SeoMetaPolicy/ShippingMethodPolicy/
    // ShippingZonePolicy used the default BasePolicy key (their underscored
    // $model, e.g. 'seo_meta') with no $permissionKey override, but the
    // seeded permissions use spaces ('seo meta') — the exact same mismatch
    // class as RefundRequestPolicy's already-fixed bug. Latent (no seeded
    // role was ever granted these permissions), but would silently deny
    // access forever if anyone tried to grant one via the Roles UI.

    #[Test]
    public function seo_meta_policy_now_matches_seeded_permission_spelling(): void
    {
        // Page::factory()'s default 'created_by' => null violates the pages
        // table's NOT NULL constraint — a pre-existing, unrelated factory
        // gap (nothing in the suite exercised Page::factory() bare before
        // this test). Not this chunk's concern; worked around directly.
        $page = \App\Models\Page::factory()->create(['created_by' => $this->adminWithRole('super_admin')->id]);
        $seoMeta = SeoMeta::factory()->create(['metable_type' => \App\Models\Page::class, 'metable_id' => $page->id]);
        $editor = $this->adminWithPermissions('seo_meta_editor_test', ['view seo meta', 'create seo meta', 'edit seo meta', 'delete seo meta']);

        $this->assertTrue($editor->can('viewAny', SeoMeta::class));
        $this->assertTrue($editor->can('create', SeoMeta::class));
        $this->assertTrue($editor->can('update', $seoMeta));
        $this->assertTrue($editor->can('delete', $seoMeta));
    }

    #[Test]
    public function shipping_method_policy_now_matches_seeded_permission_spelling(): void
    {
        $zone = ShippingZone::factory()->create();
        $method = ShippingMethod::create([
            'zone_id' => $zone->id,
            'name' => ['en' => 'Test Method'],
            'flat_rate' => '5.00',
            'estimated_days_min' => 1,
            'estimated_days_max' => 3,
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $editor = $this->adminWithPermissions('shipping_method_editor_test', ['view shipping methods', 'create shipping methods', 'edit shipping methods', 'delete shipping methods']);

        $this->assertTrue($editor->can('viewAny', ShippingMethod::class));
        $this->assertTrue($editor->can('create', ShippingMethod::class));
        $this->assertTrue($editor->can('update', $method));
        $this->assertTrue($editor->can('delete', $method));
    }

    #[Test]
    public function shipping_zone_policy_now_matches_seeded_permission_spelling(): void
    {
        $zone = ShippingZone::factory()->create();
        $editor = $this->adminWithPermissions('shipping_zone_editor_test', ['view shipping zones', 'create shipping zones', 'edit shipping zones', 'delete shipping zones']);

        $this->assertTrue($editor->can('viewAny', ShippingZone::class));
        $this->assertTrue($editor->can('create', ShippingZone::class));
        $this->assertTrue($editor->can('update', $zone));
        $this->assertTrue($editor->can('delete', $zone));
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

    // ── Marketing module deep gap analysis (Option W): NewsletterSubscriberResource's
    // bulkToggleActive had zero ->authorize(). No seeded role holds any 'newsletters'
    // permission today (the resource is super_admin-only in practice), so this is
    // Tier 2 defense-in-depth, same as the row/bulk-action gaps fixed in Option V — a
    // synthetic editor role proves the gate now genuinely blocks an unauthorized one.

    #[Test]
    public function newsletter_subscriber_bulk_toggle_active_requires_update_permission(): void
    {
        $subscriber = NewsletterSubscriber::factory()->create(['is_active' => true]);
        $viewOnly = $this->adminWithPermissions('newsletter_sub_bulk_view_only_test', ['view newsletters']);
        $editor = $this->adminWithPermissions('newsletter_sub_bulk_editor_test', ['view newsletters', 'edit newsletters']);

        $this->actingAs($viewOnly, 'admin');
        Livewire::test(ListNewsletterSubscribers::class)->assertTableBulkActionHidden('bulkToggleActive');

        $this->actingAs($editor, 'admin');
        Livewire::test(ListNewsletterSubscribers::class)
            ->assertTableBulkActionVisible('bulkToggleActive')
            ->callTableBulkAction('bulkToggleActive', [$subscriber]);
        $this->assertFalse($subscriber->refresh()->is_active);
    }

    // ── System module deep gap analysis (Option Z) ──────────────────────────

    // TranslationResource had zero enforced authorization: AuthServiceProvider
    // mapped a Policy to a model class (Translation::class) that doesn't exist
    // anywhere in the codebase, while the resource's real model (LanguageString)
    // had no policy registered at all — same "no policy → Filament allows by
    // default" bug class as the Catalog module's ConditionPolicy finding.

    #[Test]
    public function translation_resource_now_enforces_authorization(): void
    {
        $string = LanguageString::create(['lang_code' => 'en', 'group' => 'test', 'key' => 'greeting', 'value' => 'Hello']);
        $unprivileged = $this->adminWithPermissions('translations_none_test', []);
        $editor = $this->adminWithPermissions('translations_editor_test', ['view translations', 'edit translations']);

        $this->assertFalse($unprivileged->can('viewAny', LanguageString::class));
        $this->assertFalse($unprivileged->can('update', $string));

        $this->assertTrue($editor->can('viewAny', LanguageString::class));
        $this->assertTrue($editor->can('update', $string));
        $this->assertFalse($editor->can('delete', $string), 'edit translations does not imply delete');
    }

    // EmailLogPolicy/SearchLogPolicy/FailedSearchLogPolicy all extended the
    // shared LogPolicy without overriding $permissionKey, so getKey() fell back
    // to the underscored $model name (e.g. 'email_logs') while RolesSeeder only
    // ever seeds the space-separated form ('view email logs') — same
    // permission-key-mismatch bug class as Options K/Q/R, just on the 3 log
    // policies that never got the override ActivityLog/LoginLog/CronLog already had.

    #[Test]
    public function email_log_policy_resolves_to_seeded_space_separated_permission(): void
    {
        $log = EmailLog::factory()->create();
        $viewer = $this->adminWithPermissions('email_logs_viewer_test', ['view email logs']);
        $superAdmin = $this->adminWithRole('super_admin');

        $this->assertTrue($viewer->can('view', $log));
        $this->assertTrue($superAdmin->can('view', $log));
    }

    #[Test]
    public function search_log_policy_resolves_to_seeded_space_separated_permission(): void
    {
        $log = SearchLog::factory()->create();
        $viewer = $this->adminWithPermissions('search_logs_viewer_test', ['view search logs']);

        $this->assertTrue($viewer->can('view', $log));
    }

    #[Test]
    public function failed_search_log_policy_resolves_to_seeded_space_separated_permission(): void
    {
        $log = FailedSearchLog::factory()->create();
        $viewer = $this->adminWithPermissions('failed_search_logs_viewer_test', ['view failed search logs']);

        $this->assertTrue($viewer->can('view', $log));
    }

    // AdminUi::exportCsvBulkAction() (used by 39 resources) had zero
    // ->authorize() at all — fixed at the shared-helper level by deriving the
    // owning table's model and checking 'viewAny' against it, with zero
    // per-call-site changes required. Verified on 2 representative resources.

    #[Test]
    public function export_csv_bulk_action_requires_viewany_permission(): void
    {
        $admin = Admin::factory()->create();
        $viewer = $this->adminWithPermissions('admins_export_viewer_test', ['view admins']);

        $this->actingAs($viewer, 'admin');
        Livewire::test(ListAdmins::class)
            ->assertTableBulkActionVisible('exportCsv')
            ->callTableBulkAction('exportCsv', [$admin]);
    }

    #[Test]
    public function export_csv_bulk_action_resolves_correct_model_on_a_second_resource(): void
    {
        // Proves the shared helper's authorize closure genuinely derives the
        // model from each table it's attached to (Admin::class above,
        // Carrier::class here) rather than being coincidentally correct for
        // one resource only.
        $carrier = Carrier::create(['name' => 'Test Carrier', 'tracking_url' => 'https://example.test/{tracking_no}']);
        $viewer = $this->adminWithPermissions('carriers_export_viewer_test', ['view carriers']);

        $this->actingAs($viewer, 'admin');
        Livewire::test(ListCarriers::class)
            ->assertTableBulkActionVisible('exportCsv')
            ->callTableBulkAction('exportCsv', [$carrier]);
    }

    // ── Content module deep gap analysis (Option Y) ─────────────────────────

    // BlogTag/MenuItem had zero registered Policy anywhere — reachable only
    // via TagsRelationManager/MenuItemRelationManager (no standalone
    // resource), so any authenticated admin of any role could manage them.
    // Same bug class as the Catalog module's ConditionPolicy finding.

    #[Test]
    public function blog_tag_policy_resolves_to_the_blog_permission(): void
    {
        $tag = BlogTag::create(['name' => ['en' => 'Test Tag'], 'slug' => 'test-tag-'.uniqid()]);
        $unprivileged = $this->adminWithPermissions('blog_tags_none_test', []);
        $editor = $this->adminWithPermissions('blog_tags_editor_test', ['view blog', 'edit blog']);

        $this->assertFalse($unprivileged->can('viewAny', BlogTag::class));
        $this->assertFalse($unprivileged->can('update', $tag));

        $this->assertTrue($editor->can('viewAny', BlogTag::class));
        $this->assertTrue($editor->can('update', $tag));
    }

    #[Test]
    public function menu_item_policy_resolves_to_the_menus_permission(): void
    {
        $menu = Menu::create(['name' => 'Test Menu', 'location' => 'header', 'lang' => 'en']);
        $item = MenuItem::create(['menu_id' => $menu->id, 'label' => ['en' => 'Test'], 'url' => '/test', 'sort_order' => 0]);
        $unprivileged = $this->adminWithPermissions('menu_items_none_test', []);
        $editor = $this->adminWithPermissions('menu_items_editor_test', ['view menus', 'edit menus']);

        $this->assertFalse($unprivileged->can('viewAny', MenuItem::class));
        $this->assertFalse($unprivileged->can('update', $item));

        $this->assertTrue($editor->can('viewAny', MenuItem::class));
        $this->assertTrue($editor->can('update', $item));
    }

    // AdminUi::impactBulkAction() (18 call sites across Catalog + Content +
    // Commerce + Marketing) had zero authorization built in — 12 of those 18
    // never added their own ->authorize() at the call site either. Fixed at
    // the shared-helper level (same pattern as exportCsvBulkAction in Option
    // Z); verified the fix doesn't disturb the 6 call sites that already
    // chain their own ->authorize() (Filament's authorize() replaces, not
    // merges, so the more specific call-site override always wins).

    #[Test]
    public function impact_bulk_action_default_gate_protects_a_previously_unprotected_content_action(): void
    {
        $post = BlogPost::factory()->create(['status' => 'draft']);
        $viewOnly = $this->adminWithPermissions('blog_bulk_view_only_test', ['view blog']);
        $editor = $this->adminWithPermissions('blog_bulk_editor_test', ['view blog', 'edit blog']);

        $this->actingAs($viewOnly, 'admin');
        Livewire::test(ListBlogPosts::class)->assertTableBulkActionHidden('bulkPublish');

        $this->actingAs($editor, 'admin');
        Livewire::test(ListBlogPosts::class)
            ->assertTableBulkActionVisible('bulkPublish')
            ->callTableBulkAction('bulkPublish', [$post]);
        $this->assertSame('published', $post->refresh()->status->value);
    }

    #[Test]
    public function impact_bulk_action_default_gate_protects_a_previously_unprotected_catalog_action(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => false]);
        $viewOnly = $this->adminWithPermissions('manufacturers_bulk_view_only_test', ['view manufacturers']);
        $editor = $this->adminWithPermissions('manufacturers_bulk_editor_test', ['view manufacturers', 'edit manufacturers']);

        $this->actingAs($viewOnly, 'admin');
        Livewire::test(ListManufacturers::class)->assertTableBulkActionHidden('bulkActivate');

        $this->actingAs($editor, 'admin');
        Livewire::test(ListManufacturers::class)
            ->assertTableBulkActionVisible('bulkActivate')
            ->callTableBulkAction('bulkActivate', [$manufacturer]);
        $this->assertTrue($manufacturer->refresh()->is_active);
    }

    // RolesSeeder only ever seeded 'view'/'edit testimonials' — 'create'/
    // 'delete testimonials' didn't exist under any spelling, so no role
    // could ever be granted them even though TestimonialPolicy (via
    // BasePolicy) checks for exactly those abilities.

    #[Test]
    public function testimonial_create_and_delete_permissions_are_now_grantable(): void
    {
        $testimonial = Testimonial::factory()->create();
        $editor = $this->adminWithPermissions('testimonials_full_test', [
            'view testimonials', 'create testimonials', 'edit testimonials', 'delete testimonials',
        ]);

        $this->assertTrue($editor->can('create', Testimonial::class));
        $this->assertTrue($editor->can('delete', $testimonial));
    }

    // Option CC — CarModelResource's table was reported to throw a fatal
    // error during ANY bulk-action visibility/authorization check for ANY
    // non-super_admin role, reproducing even on the stock DeleteBulkAction
    // and AdminUi::exportCsvBulkAction() (i.e. unrelated to the
    // impactBulkAction() authorization-closure fix above). Root cause:
    // CarModelPolicy's $model = 'car_models' (underscore) had no
    // $permissionKey override, so every ability check called
    // $admin->can('edit car_models') / 'delete car_models' — but
    // RolesSeeder only ever seeds the space-separated 'edit car models' /
    // 'delete car models'. Same bug class as EmailLogPolicy/SearchLogPolicy/
    // FailedSearchLogPolicy (Option Z) and NewsletterSubscriberPolicy
    // (Option R).

    #[Test]
    public function car_model_bulk_actions_do_not_crash_for_non_super_admin_roles(): void
    {
        $carModel = CarModel::factory()->create(['is_active' => false]);
        $viewOnly = $this->adminWithPermissions('car_models_bulk_view_only_test', ['view car models']);
        $editor = $this->adminWithPermissions('car_models_bulk_editor_test', ['view car models', 'edit car models', 'delete car models']);

        $this->actingAs($viewOnly, 'admin');
        Livewire::test(ListCarModels::class)
            ->assertTableBulkActionHidden('activate')
            ->assertTableBulkActionHidden('delete')
            ->assertTableBulkActionVisible('exportCsv');

        $this->actingAs($editor, 'admin');
        Livewire::test(ListCarModels::class)
            ->assertTableBulkActionVisible('activate')
            ->callTableBulkAction('activate', [$carModel])
            ->assertTableBulkActionVisible('delete')
            ->callTableBulkAction('delete', [$carModel]);

        $this->assertModelMissing($carModel);
    }
}
