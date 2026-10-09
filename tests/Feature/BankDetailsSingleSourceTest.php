<?php

namespace Tests\Feature;

use App\Enums\SettingType;
use App\Models\Admin;
use App\Models\InvoiceBankAccount;
use App\Models\Order;
use App\Models\Setting;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Database\Seeders\AdminSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Sales → Bank Accounts is the single source of the store's bank details. */
class BankDetailsSingleSourceTest extends TestCase
{
    use RefreshDatabase;

    private function setting(string $group, string $key, string $value): void
    {
        Setting::updateOrCreate(['group' => $group, 'key' => $key], ['value' => $value, 'type' => SettingType::String]);
        Cache::flush();
    }

    private function account(array $overrides = []): InvoiceBankAccount
    {
        return InvoiceBankAccount::create(array_merge([
            'label' => 'Main', 'currency' => 'EUR', 'account_holder' => 'UAB OeParts', 'bank_name' => 'SEB',
            'iban' => 'LT601010012345678901', 'bic' => 'CBVILT2X', 'is_active' => true, 'sort_order' => 0,
        ], $overrides));
    }

    private function legacy(): void
    {
        $this->setting('payment', 'bank_iban', 'DE89370400440532013000');
        $this->setting('payment', 'bank_name', 'Settings Bank');
        $this->setting('payment', 'bank_bic', 'COBADEFFXXX');
        $this->setting('payment', 'bank_account_holder', 'Settings Holder');
    }

    #[Test]
    public function a_bank_account_wins_over_the_old_settings_account(): void
    {
        $this->legacy();
        $this->account();

        $bank = app(InvoiceService::class)->bankDetails();

        $this->assertSame('LT60 1010 0123 4567 8901', $bank['iban']);
        $this->assertSame('SEB', $bank['bank_name']);
    }

    #[Test]
    public function the_account_for_the_currency_is_preferred_and_inactive_ones_are_ignored(): void
    {
        $this->account(['label' => 'EUR', 'currency' => 'EUR', 'sort_order' => 0]);
        $this->account(['label' => 'USD', 'currency' => 'USD', 'iban' => 'GB82WEST12345698765432', 'bank_name' => 'Wise', 'sort_order' => 1]);
        $this->account(['label' => 'Old', 'currency' => 'GBP', 'iban' => 'FR1420041010050500013M02606', 'is_active' => false, 'sort_order' => 2]);

        $service = app(InvoiceService::class);

        $this->assertSame('Wise', $service->bankDetails('USD')['bank_name']);
        $this->assertSame('SEB', $service->bankDetails('GBP')['bank_name'], 'no active GBP account: the default account is used, never the inactive one');
        $this->assertSame('SEB', $service->bankDetails()['bank_name']);
    }

    #[Test]
    public function settings_are_only_a_fallback_when_no_account_exists(): void
    {
        $this->legacy();

        $this->assertSame('Settings Bank', app(InvoiceService::class)->bankDetails()['bank_name']);

        $this->setting('payment', 'bank_iban', '');
        $this->assertNull(app(InvoiceService::class)->bankDetails());
    }

    #[Test]
    public function storefront_bank_transfer_details_come_from_the_bank_account(): void
    {
        $this->legacy();
        $this->account();
        $order = Order::factory()->create(['grand_total' => '100.00']);

        $details = app(PaymentService::class)->getBankTransferDetails($order);

        $this->assertSame('LT60 1010 0123 4567 8901', $details['iban']);
        $this->assertSame('CBVILT2X', $details['bic']);
        $this->assertSame('UAB OeParts', $details['account_holder']);
    }

    #[Test]
    public function an_account_without_a_bic_is_still_usable_at_checkout(): void
    {
        $this->account(['bic' => null]);
        $order = Order::factory()->create();

        $details = app(PaymentService::class)->getBankTransferDetails($order);

        $this->assertSame('', $details['bic']);
    }

    #[Test]
    public function checkout_refuses_cleanly_when_no_bank_details_exist_anywhere(): void
    {
        $this->setting('payment', 'bank_iban', '');
        $order = Order::factory()->create();

        $this->expectExceptionMessage('Bank transfer details not configured.');

        app(PaymentService::class)->getBankTransferDetails($order);
    }

    // ---- the one-time copy ------------------------------------------------------------------

    private function runCopy(): void
    {
        (require base_path('database/migrations/2026_10_09_000009_copy_settings_bank_into_bank_accounts.php'))->up();
    }

    #[Test]
    public function the_settings_account_is_copied_into_bank_accounts_once(): void
    {
        $this->setting('payment', 'bank_iban', 'lt60 1010 0123 4567 8901');
        $this->setting('payment', 'bank_name', 'SEB');
        $this->setting('payment', 'bank_bic', 'cbvilt2x');
        $this->setting('payment', 'bank_account_holder', 'UAB OeParts');
        $this->setting('general', 'currency', 'EUR');

        $this->runCopy();
        $this->runCopy();

        $this->assertSame(1, InvoiceBankAccount::count());
        $account = InvoiceBankAccount::firstOrFail();
        $this->assertSame('LT601010012345678901', $account->iban);
        $this->assertSame('CBVILT2X', $account->bic);
        $this->assertSame('EUR', $account->currency);
        $this->assertTrue($account->is_active);
        $this->assertSame('lt60 1010 0123 4567 8901', Setting::where('group', 'payment')->where('key', 'bank_iban')->value('value'), 'the Settings value is left untouched');
    }

    #[Test]
    public function the_demo_placeholder_empty_settings_and_existing_accounts_are_not_copied(): void
    {
        $this->setting('payment', 'bank_iban', 'DE89 3704 0044 0532 0130 00');
        $this->runCopy();
        $this->assertSame(0, InvoiceBankAccount::count(), 'the seeder\'s sample IBAN is not a real account');

        $this->setting('payment', 'bank_iban', '');
        $this->runCopy();
        $this->assertSame(0, InvoiceBankAccount::count());

        $this->setting('payment', 'bank_iban', 'LT601010012345678901');
        $this->account(['label' => 'Already here']);
        $this->runCopy();
        $this->assertSame(1, InvoiceBankAccount::count());
    }

    // ---- the Settings page ----------------------------------------------------------------------

    #[Test]
    public function the_settings_page_points_to_bank_accounts_instead_of_a_second_form(): void
    {
        $this->seed([RolesSeeder::class, AdminSeeder::class]);
        $admin = Admin::where('email', 'superadmin@oeparts.test')->firstOrFail();
        $this->account();

        $response = $this->actingAs($admin, 'admin')->get('/admin/settings/store-operations-settings');

        $response->assertOk();
        $response->assertSee('Sales → Bank Accounts', false);
        $response->assertSee('LT60 1010 0123 4567 8901', false);
        $response->assertDontSee('IBAN Account Number', false);
    }
}
