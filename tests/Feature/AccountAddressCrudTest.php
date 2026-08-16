<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression guard for a severe, previously-shipped bug found during a
 * full manual QA pass (2026-08-16): saving an address via the account
 * address book was completely broken for EVERY customer, always.
 *
 * Two independent bugs stacked on top of each other:
 * 1. 'state' was validated as required, even though user_addresses.state
 *    is nullable, checkout's own address step never collects a state at
 *    all (orders has no shipping_state column), and most European
 *    addresses don't use one — this blocked the request before it ever
 *    reached the database.
 * 2. Underneath that: AccountController::saveAddress() passed the
 *    validated request straight into UserAddress::create()/update() using
 *    the form's field names (address_line_1/address_line_2, matching
 *    every other address form in the app) — but the real database columns
 *    are address_line1/address_line2 (no underscore before the digit).
 *    UserAddress::$fillable correctly lists the real column names, so
 *    Eloquent silently DROPPED both fields on every mass-assignment
 *    (unknown keys are ignored, not an error) — and since address_line1
 *    is NOT NULL with no default, the INSERT failed outright with a
 *    500. Bug #1 masked bug #2 from ever being reached through the UI;
 *    fixing #1 alone would have just traded one total failure for another.
 *
 * Zero test coverage existed for this flow before — confirmed by grepping
 * tests/ for UserAddress: only page-load checks existed, nothing ever
 * submitted the form.
 */
class AccountAddressCrudTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::factory()->create();
    }

    #[Test]
    public function a_customer_can_create_an_address_without_a_state(): void
    {
        $user = $this->customer();

        $response = $this->actingAs($user, 'web')->post('/en/account/addresses', [
            'first_name' => 'Klaus',
            'last_name' => 'Fischer',
            'address_line_1' => 'Teststrasse 99',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'country_code' => 'DE',
            'phone' => '+49 30 1234567',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $address = UserAddress::where('user_id', $user->id)->first();
        $this->assertNotNull($address, 'Address was not persisted at all.');
        $this->assertSame('Teststrasse 99', $address->address_line1);
        $this->assertNull($address->address_line2);
        $this->assertNull($address->state);
    }

    #[Test]
    public function address_line_2_and_state_are_stored_correctly_when_provided(): void
    {
        $user = $this->customer();

        $this->actingAs($user, 'web')->post('/en/account/addresses', [
            'first_name' => 'Klaus',
            'last_name' => 'Fischer',
            'address_line_1' => '123 Main St',
            'address_line_2' => 'Suite 400',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country_code' => 'US',
        ])->assertSessionHasNoErrors();

        $address = UserAddress::where('user_id', $user->id)->sole();
        $this->assertSame('123 Main St', $address->address_line1);
        $this->assertSame('Suite 400', $address->address_line2);
        $this->assertSame('NY', $address->state);
    }

    #[Test]
    public function a_customer_can_edit_an_existing_address(): void
    {
        $user = $this->customer();
        $address = UserAddress::create([
            'user_id' => $user->id,
            'first_name' => 'Klaus',
            'last_name' => 'Fischer',
            'address_line1' => 'Old Street 1',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'country_code' => 'DE',
        ]);

        $this->actingAs($user, 'web')->post('/en/account/addresses', [
            'id' => $address->id,
            'first_name' => 'Klaus',
            'last_name' => 'Fischer',
            'address_line_1' => 'New Street 2',
            'city' => 'Munich',
            'postal_code' => '80331',
            'country_code' => 'DE',
        ])->assertSessionHasNoErrors();

        $this->assertSame('New Street 2', $address->fresh()->address_line1);
        $this->assertSame('Munich', $address->fresh()->city);
    }

    #[Test]
    public function a_customer_can_delete_their_own_address(): void
    {
        $user = $this->customer();
        $address = UserAddress::create([
            'user_id' => $user->id,
            'first_name' => 'Klaus',
            'last_name' => 'Fischer',
            'address_line1' => 'Teststrasse 99',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'country_code' => 'DE',
        ]);

        $this->actingAs($user, 'web')
            ->delete("/en/account/addresses/{$address->id}")
            ->assertRedirect();

        $this->assertModelMissing($address);
    }

    #[Test]
    public function a_customer_cannot_edit_or_delete_another_customers_address(): void
    {
        $owner = $this->customer();
        $intruder = $this->customer();
        $address = UserAddress::create([
            'user_id' => $owner->id,
            'first_name' => 'Klaus',
            'last_name' => 'Fischer',
            'address_line1' => 'Teststrasse 99',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'country_code' => 'DE',
        ]);

        // Blocked by the 'id' field's own
        // exists:user_addresses,id,user_id,{current user} rule — a
        // standard validation failure (redirect + session errors), not a
        // 404, but the address is equally untouched either way.
        $this->actingAs($intruder, 'web')->post('/en/account/addresses', [
            'id' => $address->id,
            'first_name' => 'Hacked',
            'last_name' => 'Name',
            'address_line_1' => 'Hijacked St',
            'city' => 'Nowhere',
            'postal_code' => '00000',
            'country_code' => 'DE',
        ])->assertSessionHasErrors('id');

        $this->actingAs($intruder, 'web')
            ->delete("/en/account/addresses/{$address->id}")
            ->assertStatus(404);

        $this->assertSame('Teststrasse 99', $address->fresh()->address_line1);
    }
}
