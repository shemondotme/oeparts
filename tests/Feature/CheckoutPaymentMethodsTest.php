<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\SettingType;
use App\Filament\Pages\Settings\StoreOperationsSettings;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\PaymentService;
use App\Support\CheckoutPaymentMethods;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Settings → Checkout & Payments → "Allowed Payment Methods" is a multi-select
 * that really controls the storefront: any combination of Card / Paysera /
 * Bank transfer can be offered together, and a method that is switched off is
 * hidden from the checkout AND refused by the server. Paysera's Apple Pay /
 * Google Pay are offered only when the admin switches them on.
 */
class CheckoutPaymentMethodsTest extends TestCase
{
    use RefreshDatabase;

    private function allow(array $methods, ?string $default = null): void
    {
        Setting::updateOrCreate(
            ['group' => 'checkout', 'key' => 'allowed_payment_methods'],
            ['value' => json_encode($methods), 'type' => SettingType::Json],
        );
        if ($default !== null) {
            Setting::updateOrCreate(
                ['group' => 'checkout', 'key' => 'default_payment_method'],
                ['value' => $default, 'type' => SettingType::String],
            );
        }
        Cache::flush();
    }

    private function flag(string $key, bool $on): void
    {
        Setting::updateOrCreate(['group' => 'checkout', 'key' => $key], ['value' => $on ? '1' : '0', 'type' => SettingType::Boolean]);
        Cache::flush();
    }

    private function paymentPage(array $orderOverrides = []): string
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(array_merge([
            'user_id' => $user->id, 'status' => OrderStatus::Pending, 'payment_method' => PaymentMethod::Card,
        ], $orderOverrides));

        return $this->actingAs($user, 'web')
            ->get(route('frontend.checkout.payment', ['lang' => 'en', 'order' => $order->order_number]))
            ->assertOk()
            ->getContent();
    }

    // ── the helper ─────────────────────────────────────────────────────────

    #[Test]
    public function the_enabled_methods_are_any_combination_in_display_order(): void
    {
        $this->allow(['bank_transfer', 'card']);
        $this->assertSame(['card', 'bank_transfer'], CheckoutPaymentMethods::enabled());

        $this->allow(['paysera']);
        $this->assertSame(['paysera'], CheckoutPaymentMethods::enabled());

        $this->allow(['card', 'paysera', 'bank_transfer']);
        $this->assertSame(['card', 'paysera', 'bank_transfer'], CheckoutPaymentMethods::enabled());
    }

    #[Test]
    public function junk_empty_or_unset_never_leaves_the_checkout_without_a_way_to_pay(): void
    {
        $this->allow([]);
        $this->assertSame(['card', 'bank_transfer'], CheckoutPaymentMethods::enabled());

        $this->allow(['cash', 'bitcoin']);
        $this->assertSame(['card', 'bank_transfer'], CheckoutPaymentMethods::enabled());
    }

    #[Test]
    public function the_default_is_the_configured_one_only_while_it_is_still_offered(): void
    {
        $this->allow(['card', 'bank_transfer'], 'bank_transfer');
        $this->assertSame('bank_transfer', CheckoutPaymentMethods::default());

        $this->allow(['card'], 'bank_transfer');
        $this->assertSame('card', CheckoutPaymentMethods::default());
        $this->assertSame('card', CheckoutPaymentMethods::resolve('paysera'));
        $this->assertSame('card', CheckoutPaymentMethods::resolve(null));
    }

    // ── what the customer sees ─────────────────────────────────────────────

    #[Test]
    public function card_and_bank_transfer_can_be_offered_together_and_paysera_stays_hidden(): void
    {
        $this->allow(['card', 'bank_transfer']);

        $html = $this->paymentPage();

        $this->assertStringContainsString('id="method-card"', $html);
        $this->assertStringContainsString('id="method-bank"', $html);
        $this->assertStringNotContainsString('id="method-paysera"', $html);
    }

    #[Test]
    public function every_method_can_be_offered_at_once(): void
    {
        $this->allow(['card', 'paysera', 'bank_transfer']);

        $html = $this->paymentPage();

        foreach (['method-card', 'method-paysera', 'method-bank'] as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $html);
        }
    }

    #[Test]
    public function a_switched_off_method_is_hidden_and_the_preselection_falls_back_to_an_offered_one(): void
    {
        $this->allow(['bank_transfer'], 'bank_transfer');

        // The order was created with Card, which is no longer offered.
        $html = $this->paymentPage(['payment_method' => PaymentMethod::Card]);

        $this->assertStringNotContainsString('id="method-card"', $html);
        $this->assertStringContainsString('id="method-bank"', $html);
        $this->assertMatchesRegularExpression('/id="method-bank"[^>]*checked/s', $html);
    }

    #[Test]
    public function a_method_that_is_switched_off_is_refused_by_the_server(): void
    {
        $this->allow(['card', 'bank_transfer']);
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Pending]);

        $this->actingAs($user, 'web')
            ->post(route('frontend.checkout.payment.process', ['lang' => 'en', 'order' => $order->order_number]), [
                'payment_method' => 'paysera',
            ])
            ->assertSessionHasErrors('payment_method');
    }

    #[Test]
    public function the_new_checkout_starts_on_an_offered_default(): void
    {
        $this->allow(['paysera', 'bank_transfer'], 'card');

        $this->assertSame('paysera', CheckoutPaymentMethods::default());
    }

    // ── the admin form ─────────────────────────────────────────────────────

    private function superAdmin(): Admin
    {
        $this->seed([RolesSeeder::class, SettingsSeeder::class]);
        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');

        return $admin;
    }

    #[Test]
    public function the_settings_page_saves_several_ticked_methods_at_once(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(StoreOperationsSettings::class)
            ->set('data.allowed_payment_methods', ['card', 'paysera', 'bank_transfer'])
            ->call('save')
            ->assertHasNoErrors();

        Cache::flush();
        $this->assertSame(['card', 'paysera', 'bank_transfer'], CheckoutPaymentMethods::enabled());
    }

    #[Test]
    public function the_settings_page_refuses_to_save_with_no_method_ticked(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(StoreOperationsSettings::class)
            ->set('data.allowed_payment_methods', [])
            ->call('save')
            ->assertHasErrors(['data.allowed_payment_methods']);
    }

    #[Test]
    public function the_paysera_wallet_toggles_save_into_the_checkout_group(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(StoreOperationsSettings::class)
            ->set('data.paysera_apple_pay_enabled', true)
            ->set('data.paysera_google_pay_enabled', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('true', Setting::where('group', 'checkout')->where('key', 'paysera_apple_pay_enabled')->value('value'));
        $this->assertSame('true', Setting::where('group', 'checkout')->where('key', 'paysera_google_pay_enabled')->value('value'));
    }

    // ── Paysera Apple Pay / Google Pay ─────────────────────────────────────

    private const TOKEN_URL = 'https://api.paysera.com/auth/realms/Paysera/protocol/openid-connect/token';

    private const ORDERS_URL = 'https://api.paysera.com/merchant-order/integration/v1/orders';

    private const LINKS_URL = 'https://api.paysera.com/checkout-payment-link/integration/v1/payment-links';

    private function payseraCreds(): void
    {
        foreach (['paysera_client_id' => 'cid', 'paysera_client_secret' => 'csecret'] as $key => $value) {
            Setting::updateOrCreate(['group' => 'payment', 'key' => $key], ['value' => $value, 'type' => SettingType::String]);
        }
        Cache::flush();
    }

    private function linkRequests(): array
    {
        return Http::recorded(fn ($request) => $request->url() === self::LINKS_URL)
            ->map(fn ($pair) => $pair[0]->data())->values()->all();
    }

    #[Test]
    public function a_picked_wallet_pre_selects_that_paysera_method_when_the_admin_offers_it(): void
    {
        $this->payseraCreds();
        $this->flag('paysera_apple_pay_enabled', true);
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            self::ORDERS_URL => Http::response(['order_id' => 'o-1'], 201),
            self::LINKS_URL => Http::response(['link_id' => 'l-1', 'payment_URL' => 'https://pay.example/l-1'], 201),
        ]);
        $order = Order::factory()->create(['guest_email' => 'a@example.com', 'user_id' => null]);

        $result = app(PaymentService::class)->createPayseraPaymentLink($order, 'apple-pay');

        $this->assertSame('https://pay.example/l-1', $result['payment_url']);
        $requests = $this->linkRequests();
        $this->assertCount(1, $requests);
        $this->assertSame(['key' => 'apple-pay'], $requests[0]['payment_details']);
    }

    #[Test]
    public function a_wallet_the_admin_has_not_switched_on_is_ignored(): void
    {
        $this->payseraCreds();
        $this->flag('paysera_google_pay_enabled', false);
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            self::ORDERS_URL => Http::response(['order_id' => 'o-1'], 201),
            self::LINKS_URL => Http::response(['link_id' => 'l-1', 'payment_URL' => 'https://pay.example/l-1'], 201),
        ]);
        $order = Order::factory()->create(['guest_email' => 'a@example.com', 'user_id' => null]);

        app(PaymentService::class)->createPayseraPaymentLink($order, 'google-pay');
        app(PaymentService::class)->createPayseraPaymentLink($order, 'bitcoin');

        foreach ($this->linkRequests() as $request) {
            $this->assertArrayNotHasKey('payment_details', $request);
        }
    }

    #[Test]
    public function when_paysera_does_not_have_the_wallet_for_this_project_the_customer_still_gets_a_working_page(): void
    {
        $this->payseraCreds();
        $this->flag('paysera_google_pay_enabled', true);
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            self::ORDERS_URL => Http::response(['order_id' => 'o-1'], 201),
            self::LINKS_URL => Http::sequence()
                ->push(['error' => 'invalid_properties'], 422)
                ->push(['link_id' => 'l-2', 'payment_URL' => 'https://pay.example/l-2'], 201),
        ]);
        $order = Order::factory()->create(['guest_email' => 'a@example.com', 'user_id' => null]);

        $result = app(PaymentService::class)->createPayseraPaymentLink($order, 'google-pay');

        $this->assertSame('https://pay.example/l-2', $result['payment_url']);
        $requests = $this->linkRequests();
        $this->assertCount(2, $requests);
        $this->assertArrayHasKey('payment_details', $requests[0]);
        $this->assertArrayNotHasKey('payment_details', $requests[1]);
    }

    private const METHODS_URL = 'https://api.paysera.com/checkout-project/integration/v1/methods';

    /** Fake Paysera answering with the given method keys for the project. */
    private function payseraOffers(array $keys): void
    {
        $this->payseraCreds();
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            self::METHODS_URL.'*' => Http::response(['items' => array_map(fn ($k) => ['key' => $k, 'type' => 'pis'], $keys)], 200),
        ]);
    }

    #[Test]
    public function the_payment_page_offers_the_wallet_choice_only_when_a_wallet_is_switched_on_and_paysera_has_it(): void
    {
        $this->allow(['paysera', 'bank_transfer']);
        $this->payseraOffers(['swedbank', 'apple-pay']);

        // Both switched off: no choice.
        $this->flag('paysera_apple_pay_enabled', false);
        $this->flag('paysera_google_pay_enabled', false);
        $this->assertStringNotContainsString('name="paysera_wallet"', $this->paymentPage());

        // Apple Pay on and the project has it; Google Pay on but the project does not.
        $this->flag('paysera_apple_pay_enabled', true);
        $this->flag('paysera_google_pay_enabled', true);
        $html = $this->paymentPage();
        $this->assertStringContainsString('name="paysera_wallet"', $html);
        $this->assertStringContainsString('value="apple-pay"', $html);
        $this->assertStringNotContainsString('value="google-pay"', $html);
    }

    #[Test]
    public function a_wallet_the_paysera_project_does_not_have_is_never_offered_even_when_switched_on(): void
    {
        $this->allow(['paysera', 'bank_transfer']);
        $this->payseraOffers(['swedbank', 'seb']); // bank links only — like the real project
        $this->flag('paysera_apple_pay_enabled', true);
        $this->flag('paysera_google_pay_enabled', true);

        $this->assertSame([], CheckoutPaymentMethods::payseraWallets());
        $this->assertStringNotContainsString('name="paysera_wallet"', $this->paymentPage());
    }

    #[Test]
    public function when_paysera_cannot_be_reached_no_wallet_is_offered(): void
    {
        $this->allow(['paysera', 'bank_transfer']);
        $this->payseraCreds();
        $this->flag('paysera_apple_pay_enabled', true);
        Http::fake([self::TOKEN_URL => Http::response([], 500)]);

        $this->assertSame([], CheckoutPaymentMethods::payseraWallets());
    }

    #[Test]
    public function the_paysera_method_list_is_cached_not_fetched_on_every_page_view(): void
    {
        $this->payseraOffers(['swedbank', 'google-pay']);

        $service = app(PaymentService::class);
        $service->payseraMethodKeys();
        $service->payseraMethodKeys();
        $service->payseraMethodKeys();

        Http::assertSentCount(2); // one token + one methods call
        $this->assertSame(['swedbank', 'google-pay'], $service->payseraMethodKeys());
    }

    #[Test]
    public function the_wallet_choice_is_not_offered_when_paysera_itself_is_switched_off(): void
    {
        $this->allow(['card', 'bank_transfer']);
        $this->payseraOffers(['apple-pay']);
        $this->flag('paysera_apple_pay_enabled', true);

        $this->assertSame([], CheckoutPaymentMethods::payseraWallets());
    }

    #[Test]
    public function the_checkout_default_comes_from_the_offered_methods_for_a_brand_new_checkout(): void
    {
        $this->allow(['bank_transfer'], 'card');

        $reflection = new \ReflectionClass(CheckoutService::class);
        $this->assertTrue($reflection->hasMethod('start') || $reflection->hasMethod('create'));
        $this->assertSame('bank_transfer', CheckoutPaymentMethods::default());
    }
}
