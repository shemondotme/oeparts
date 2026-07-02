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
            'contact.success_message'     => ['contact.success_message', 'Your message has been sent successfully. We will get back to you soon.'],
            'orders.expected_delivery_days' => ['orders.expected_delivery_days', 5],
            'dashboard.orders_threshold'        => ['dashboard.orders_threshold', 50],
            'dashboard.pending_delayed_minutes' => ['dashboard.pending_delayed_minutes', 120],
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
    public function orders_expected_delivery_days_replaces_the_wrong_group_name(): void
    {
        $this->assertNotSame('SENTINEL', settings('orders.expected_delivery_days', 'SENTINEL'));
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
    public function new_dashboard_settings_page_loads_with_seeded_defaults(): void
    {
        $this->seed([
            \Database\Seeders\RolesSeeder::class,
            \Database\Seeders\AdminSeeder::class,
        ]);
        $admin = \App\Models\Admin::where('email', 'superadmin@oeparts.test')->firstOrFail();

        $response = $this->actingAs($admin, 'admin')->get('/admin/settings/dashboard-settings');

        $response->assertStatus(200);
        $response->assertSee('Dashboard Thresholds');
    }
}
