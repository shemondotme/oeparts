<?php

namespace Tests\Feature;

use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SettingsCompletenessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\SettingsSeeder::class);

        // SettingsService caches per-group for 5 minutes with no test-isolation —
        // a prior test elsewhere in the suite reading these groups before this
        // seeder ran would otherwise leave a stale cached array behind.
        foreach (['cart', 'search', 'checkout', 'contact', 'orders', 'dashboard', 'security', 'seo', 'announcement'] as $group) {
            app(SettingsService::class)->forget($group);
        }
    }

    public static function previouslyUndeclaredSettingsProvider(): array
    {
        return [
            'cart.rate_limit_per_minute'  => ['cart.rate_limit_per_minute', 60],
            'cart.max_quantity'           => ['cart.max_quantity', 999],
            'cart.guest_cookie_days'      => ['cart.guest_cookie_days', 7],
            'search.results_limit'        => ['search.results_limit', 50],
            'search.per_page'             => ['search.per_page', 20],
            'search.popular_days_window'  => ['search.popular_days_window', 30],
            'search.popular_limit'        => ['search.popular_limit', 8],
            'search.cache_ttl_hours'      => ['search.cache_ttl_hours', 6],
            'checkout.proof_max_size_kb'      => ['checkout.proof_max_size_kb', 5120],
            'checkout.guest_password_length'  => ['checkout.guest_password_length', 12],
            'dashboard.orders_threshold'        => ['dashboard.orders_threshold', 50],
            'dashboard.pending_delayed_minutes' => ['dashboard.pending_delayed_minutes', 120],
            'invoice.payment_terms_days'        => ['invoice.payment_terms_days', 30],
        ];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('previouslyUndeclaredSettingsProvider')]
    public function previously_undeclared_setting_resolves_to_its_seeded_value(string $key, int|string $expected): void
    {
        $resolved = is_int($expected)
            ? (int) settings($key, 'SENTINEL')
            : settings($key, 'SENTINEL');

        $this->assertNotSame('SENTINEL', settings($key, 'SENTINEL'), "{$key} fell back to the sentinel default — no seed row exists.");
        $this->assertSame($expected, $resolved);
    }

    #[Test]
    public function contact_success_message_is_seeded_as_multilingual_json(): void
    {
        $raw = settings('contact.success_message', 'SENTINEL');
        $this->assertNotSame('SENTINEL', $raw);

        $decoded = json_decode($raw, true);
        $this->assertSame(['en', 'de', 'lt', 'fr', 'es'], array_keys($decoded));
        $this->assertSame('Your message has been sent successfully. We will get back to you soon.', $decoded['en']);

        app()->setLocale('de');
        $this->assertSame($decoded['de'], settings_trans('contact.success_message'));
        app()->setLocale('en');
    }

    #[Test]
    public function search_supported_languages_is_seeded_and_json_decodes_to_the_expected_array(): void
    {
        $raw = settings('search.supported_languages', 'SENTINEL');

        $this->assertNotSame('SENTINEL', $raw);
        $this->assertSame(['en', 'de', 'lt', 'fr', 'es'], json_decode($raw, true));
    }

    #[Test]
    public function checkout_allowed_payment_methods_is_seeded_and_json_decodes_to_the_expected_array(): void
    {
        $raw = settings('checkout.allowed_payment_methods', 'SENTINEL');

        $this->assertNotSame('SENTINEL', $raw);
        $this->assertSame(['card', 'bank_transfer'], json_decode($raw, true));
    }

    #[Test]
    public function search_autocomplete_endpoint_accepts_a_supported_language_despite_json_encoded_setting(): void
    {
        $response = $this->getJson('/api/search/autocomplete?q=ABC&lang=de');

        $response->assertStatus(200);
    }

    #[Test]
    public function security_inquiry_max_per_email_is_the_correct_group(): void
    {
        $this->assertSame(10, (int) settings('security.inquiry_max_per_email', 'SENTINEL'));
    }

    #[Test]
    public function search_rate_limit_per_minute_is_the_correct_key(): void
    {
        $this->assertSame(30, (int) settings('search.rate_limit_per_minute', 'SENTINEL'));
    }

    #[Test]
    public function homepage_title_uses_the_correctly_named_seo_setting(): void
    {
        \App\Models\Setting::updateOrCreate(
            ['group' => 'seo', 'key' => 'home_title'],
            ['value' => 'Custom SEO Title For Testing', 'type' => \App\Enums\SettingType::String->value]
        );
        app(SettingsService::class)->forget('seo');

        $this->get('/en/')->assertSee('Custom SEO Title For Testing', false);
    }

    #[Test]
    public function default_meta_description_is_seeded_and_used_as_the_sitewide_fallback(): void
    {
        // layouts/app.blade.php's <meta name="description"> fallback reads
        // seo.default_description — previously unseeded and with no admin
        // field, so any page with no page-specific description (and no
        // per-entity SeoMeta override) rendered an empty description with
        // no way to fix it short of tinker.
        $seeded = settings('seo.default_description', 'SENTINEL');
        $this->assertNotSame('SENTINEL', $seeded, 'seo.default_description has no seed row.');

        \App\Models\Setting::updateOrCreate(
            ['group' => 'seo', 'key' => 'default_description'],
            ['value' => 'Custom sitewide fallback description for testing', 'type' => \App\Enums\SettingType::String->value]
        );
        app(SettingsService::class)->forget('seo');

        $response = $this->get('/en/cart');

        $response->assertSee('Custom sitewide fallback description for testing', false);
    }

    #[Test]
    public function og_description_fallback_uses_the_correctly_named_home_description_setting(): void
    {
        // layouts/app.blade.php used to read the nonexistent
        // seo.homepage_description (the real key is seo.home_description),
        // so the og:description/twitter:description fallback on every
        // non-home page always fell back to a hardcoded literal regardless
        // of what an admin configured.
        \App\Models\Setting::updateOrCreate(
            ['group' => 'seo', 'key' => 'home_description'],
            ['value' => json_encode(['en' => 'Custom OG description for testing', 'de' => '', 'lt' => '', 'fr' => '', 'es' => '']), 'type' => \App\Enums\SettingType::Json->value]
        );
        app(SettingsService::class)->forget('seo');

        $response = $this->get('/en/cart');

        $response->assertSee('Custom OG description for testing', false);
    }

    #[Test]
    public function announcement_banner_renders_localized_text_not_raw_json(): void
    {
        \App\Models\Setting::updateOrCreate(
            ['group' => 'announcement', 'key' => 'enabled'],
            ['value' => '1', 'type' => \App\Enums\SettingType::Boolean->value]
        );
        \App\Models\Setting::updateOrCreate(
            ['group' => 'announcement', 'key' => 'text'],
            ['value' => json_encode(['en' => 'Free shipping today']), 'type' => \App\Enums\SettingType::Json->value]
        );
        app(SettingsService::class)->forget('announcement');

        $response = $this->get('/en/');

        $response->assertSee('Free shipping today');
        $response->assertDontSee('{&quot;en&quot;', false);
    }

    #[Test]
    public function invoice_thank_you_text_is_seeded_as_multilingual_json(): void
    {
        $raw = settings('invoice.thank_you_text', 'SENTINEL');
        $this->assertNotSame('SENTINEL', $raw);

        $decoded = json_decode($raw, true);
        $this->assertSame(['en', 'de', 'lt', 'fr', 'es'], array_keys($decoded));
        $this->assertSame('Thank you for your business!', $decoded['en']);

        app()->setLocale('de');
        $this->assertSame($decoded['de'], settings_trans('invoice.thank_you_text'));
        app()->setLocale('en');
    }

    #[Test]
    public function invoice_pdf_renders_seeded_values_not_hardcoded_fallbacks(): void
    {
        \App\Models\Setting::updateOrCreate(
            ['group' => 'invoice', 'key' => 'payment_terms_days'],
            ['value' => '45', 'type' => \App\Enums\SettingType::Integer->value]
        );
        \App\Models\Setting::updateOrCreate(
            ['group' => 'invoice', 'key' => 'thank_you_text'],
            ['value' => json_encode(['en' => 'Custom thank-you copy for this test', 'de' => '', 'lt' => '', 'fr' => '', 'es' => '']), 'type' => \App\Enums\SettingType::Json->value]
        );
        app(SettingsService::class)->forget('invoice');

        $user = \App\Models\User::factory()->create();
        $order = \App\Models\Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => 'ORD-INV-TEST-001',
            'invoice_number' => 'INV-TEST-001',
            'shipping_name' => 'Jane Doe',
            'shipping_address_line1' => '123 Main St',
            'shipping_city' => 'Berlin',
            'shipping_postal_code' => '10115',
            'shipping_country_code' => 'DE',
            'created_at' => \Illuminate\Support\Carbon::parse('2026-01-01'),
        ]);
        $address = (object) [
            'first_name' => 'Jane', 'last_name' => 'Doe', 'company' => null,
            'address_line_1' => '123 Main St', 'address_line_2' => null,
            'city' => 'Berlin', 'state' => '', 'postal_code' => '10115',
            'country_code' => 'DE', 'phone' => null,
        ];

        $html = view('pdf.invoice', [
            'order' => $order,
            'user' => $order->user,
            'items' => $order->items,
            'billingAddress' => $address,
            'shippingAddress' => $address,
            'settings' => [
                'company_name' => 'Test Co',
                'company_address' => '',
                'company_vat' => '',
                'company_registration' => '',
                'company_email' => 'test@example.com',
                'company_phone' => '',
            ],
        ])->render();

        // Due date = order date (2026-01-01) + the seeded 45-day term, not
        // the blade's own hardcoded 30-day fallback (would render 31/01/2026).
        $this->assertStringContainsString('15/02/2026', $html);
        $this->assertStringContainsString('Custom thank-you copy for this test', $html);
        $this->assertStringNotContainsString('Thank you for your business!', $html);
    }

    #[Test]
    public function dashboard_alert_thresholds_tab_loads_with_seeded_defaults(): void
    {
        $this->seed([
            \Database\Seeders\RolesSeeder::class,
            \Database\Seeders\AdminSeeder::class,
        ]);
        $admin = \App\Models\Admin::where('email', 'superadmin@oeparts.test')->firstOrFail();

        $response = $this->actingAs($admin, 'admin')->get('/admin/settings/store-operations-settings');

        $response->assertStatus(200);
        $response->assertSee('Dashboard Alert Thresholds');
    }
}
