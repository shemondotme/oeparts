<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomInvoiceResource\Pages\CreateCustomInvoice;
use App\Filament\Resources\InvoiceClientResource\Pages\CreateInvoiceClient;
use App\Filament\Resources\InvoiceClientResource\Pages\ListInvoiceClients;
use App\Models\Admin;
use App\Models\CustomInvoice;
use App\Models\InvoiceClient;
use App\Models\User;
use App\Services\CustomInvoiceService;
use App\Services\VatNumberChecker;
use App\Services\ViesResult;
use App\Services\ViesService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Saved invoice clients (no more retyping an address on every document) and the
 * VIES check of a client's VAT number.
 */
class InvoiceClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->actingAs(Admin::factory()->create()->assignRole('super_admin'), 'admin');
    }

    private function client(array $overrides = []): InvoiceClient
    {
        return InvoiceClient::create(array_merge([
            'name' => 'Tomoko Spivey', 'company' => 'Sakamoto Engineering Co. Ltd.', 'email' => 'tomoko@sakamoto.cc',
            'phone' => '+81-463-53-3001', 'address_line1' => '2-2-24 Ogami Hiratsuka', 'city' => 'Hiratsuka',
            'state' => 'Kanagawa', 'postal_code' => '2540012', 'country_code' => 'JP', 'currency' => 'USD',
        ], $overrides));
    }

    private function invoiceForm(array $overrides = []): array
    {
        return array_merge([
            'client_name' => 'Anna Muster', 'client_company' => 'Muster GmbH', 'client_email' => 'anna@muster.de',
            'client_address_line1' => 'Teststrasse 1', 'client_city' => 'Berlin', 'client_state' => 'Berlin',
            'client_country_code' => 'DE', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(14)->toDateString(),
            'currency' => 'EUR', 'items' => [['description' => 'Parts kit', 'quantity' => 1, 'unit_price' => 100]], 'vat_rate' => 21,
        ], $overrides);
    }

    // ---- saved clients ------------------------------------------------------------------

    #[Test]
    public function a_saved_client_becomes_the_invoice_fields_including_the_state(): void
    {
        $fields = $this->client()->toInvoiceFields();

        $this->assertSame('Sakamoto Engineering Co. Ltd.', $fields['client_company']);
        $this->assertSame('Kanagawa', $fields['client_state']);
        $this->assertSame('JP', $fields['client_country_code']);
    }

    #[Test]
    public function an_invoice_can_be_saved_with_a_client_state_and_it_is_printed(): void
    {
        Livewire::test(CreateCustomInvoice::class)->fillForm($this->invoiceForm())->call('create')->assertHasNoFormErrors();

        $invoice = CustomInvoice::firstOrFail();
        $html = view('pdf.custom-invoice', app(CustomInvoiceService::class)->viewData($invoice))->render();

        $this->assertSame('Berlin', $invoice->client_state);
        $this->assertStringContainsString('Muster GmbH', $html);
    }

    #[Test]
    public function the_save_as_client_switch_stores_the_client_and_links_the_invoice(): void
    {
        Livewire::test(CreateCustomInvoice::class)
            ->fillForm($this->invoiceForm(['save_client' => true]))
            ->call('create')
            ->assertHasNoFormErrors();

        $client = InvoiceClient::firstOrFail();
        $this->assertSame('Muster GmbH', $client->company);
        $this->assertSame('Berlin', $client->state);
        $this->assertSame('EUR', $client->currency);
        $this->assertSame($client->id, CustomInvoice::firstOrFail()->client_id);
    }

    #[Test]
    public function without_the_switch_no_client_is_saved(): void
    {
        Livewire::test(CreateCustomInvoice::class)->fillForm($this->invoiceForm())->call('create')->assertHasNoFormErrors();

        $this->assertSame(0, InvoiceClient::count());
        $this->assertNull(CustomInvoice::firstOrFail()->client_id);
    }

    #[Test]
    public function picking_a_saved_client_links_the_invoice_to_it(): void
    {
        $client = $this->client();

        Livewire::test(CreateCustomInvoice::class)
            ->fillForm($this->invoiceForm(['client_id' => $client->id, 'save_client' => true]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, InvoiceClient::count(), 'a picked client is not saved again');
        $this->assertSame($client->id, CustomInvoice::firstOrFail()->client_id);
    }

    #[Test]
    public function arriving_from_a_clients_new_invoice_button_prefills_that_client(): void
    {
        $client = $this->client();

        Livewire::withQueryParams(['client' => $client->id])
            ->test(CreateCustomInvoice::class)
            ->assertFormSet([
                'client_company' => 'Sakamoto Engineering Co. Ltd.',
                'client_state' => 'Kanagawa',
                'client_country_code' => 'JP',
                'currency' => 'USD',
                'client_id' => $client->id,
            ]);
    }

    #[Test]
    public function a_client_can_be_created_and_listed_and_a_registered_customer_fills_it(): void
    {
        $user = User::factory()->create(['name' => 'Anna Muster', 'email' => 'anna@example.com']);

        Livewire::test(CreateInvoiceClient::class)
            ->fillForm(['user_id' => $user->id, 'address_line1' => 'Teststrasse 1', 'city' => 'Berlin', 'country_code' => 'DE'])
            ->call('create')
            ->assertHasNoFormErrors();

        $client = InvoiceClient::firstOrFail();
        $this->assertSame($user->id, $client->user_id);
        $this->assertSame('anna@example.com', $client->email, 'filled from the account');

        Livewire::test(ListInvoiceClients::class)->loadTable()->assertCanSeeTableRecords([$client]);
    }

    #[Test]
    public function a_client_that_has_documents_cannot_be_deleted_from_the_ui(): void
    {
        $client = $this->client();
        CustomInvoice::create(array_merge($this->invoiceForm(), [
            'invoice_number' => 'INV-C-1', 'client_id' => $client->id, 'status' => 'sent',
        ]));
        $empty = $this->client(['name' => 'No docs', 'company' => 'Empty Ltd']);

        $admin = Admin::factory()->create();
        $admin->givePermissionTo('view custom invoices', 'create custom invoices', 'edit custom invoices');
        $this->actingAs($admin, 'admin');

        $this->assertFalse($admin->can('delete', $client));
        $this->assertFalse($admin->can('delete', $empty), 'only a super admin may delete at all');
    }

    // ---- VIES --------------------------------------------------------------------------------

    private function checker(?ViesResult $result): VatNumberChecker
    {
        $vies = Mockery::mock(ViesService::class)->makePartial();
        $vies->shouldReceive('validate')->andReturn($result ?? new ViesResult(valid: null, reason: 'service_unavailable', countryCode: 'LT', vatNumber: 'x'));

        return new VatNumberChecker($vies);
    }

    #[Test]
    public function a_valid_vat_number_reports_the_business_name_and_address(): void
    {
        $r = $this->checker(new ViesResult(valid: true, reason: null, countryCode: 'LT', vatNumber: '100001919017', name: 'UAB TEST', address: 'Vilnius'))
            ->check('LT 100001919017');

        $this->assertSame('valid', $r['status']);
        $this->assertStringContainsString('UAB TEST', $r['message']);
    }

    #[Test]
    public function an_unknown_number_unreachable_service_and_non_eu_number_are_told_apart(): void
    {
        $this->assertSame('invalid', $this->checker(new ViesResult(valid: false, reason: 'invalid', countryCode: 'LT', vatNumber: 'x'))->check('LT123')['status']);
        $this->assertSame('unavailable', $this->checker(null)->check('LT123')['status']);
        $this->assertSame('not_eu', $this->checker(null)->check('JP1234567890')['status'], 'Japan is outside VIES');
        $this->assertSame('empty', $this->checker(null)->check('  ')['status']);
    }

    #[Test]
    public function a_number_without_a_prefix_uses_the_country_field_and_greece_el_means_gr(): void
    {
        $seen = [];
        $vies = Mockery::mock(ViesService::class)->makePartial();
        $vies->shouldReceive('validate')->andReturnUsing(function (string $country, string $number) use (&$seen) {
            $seen[] = [$country, $number];

            return new ViesResult(valid: true, reason: null, countryCode: $country, vatNumber: $number);
        });
        $checker = new VatNumberChecker($vies);

        $checker->check('123456789', 'DE');
        $checker->check('EL123456789');

        $this->assertSame([['DE', '123456789'], ['GR', '123456789']], $seen);
    }
}
