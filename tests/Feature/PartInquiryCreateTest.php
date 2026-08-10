<?php

namespace Tests\Feature;

use App\Filament\Resources\PartInquiryResource\Pages\CreatePartInquiry;
use App\Filament\Resources\PartInquiryResource\Pages\ViewPartInquiry;
use App\Models\Admin;
use App\Models\PartInquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * PartInquiryResource's form unconditionally readOnly()/disabled()'d every
 * field regardless of whether a record existed — the admin "New Part
 * Inquiry" page (for phone/in-person inquiries) couldn't actually be typed
 * into. Even bypassing that, submitting crashed on part_inquiries.ip_address
 * (NOT NULL, no form field, never defaulted).
 */
class PartInquiryCreateTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesSeeder::class);
        // RolesSeeder never seeds 'create inquiries' for any role (only
        // 'view'/'edit') — same latent gap as Redirects (see
        // RedirectResourceTest) — so the Create page is currently reachable
        // only via the super_admin Gate::before bypass. Unrelated to the
        // bugs under test here.
        $this->admin = Admin::factory()->create();
        $this->admin->assignRole('super_admin');
        $this->actingAs($this->admin, 'admin');
    }

    #[Test]
    public function admin_can_fill_in_and_submit_a_new_part_inquiry(): void
    {
        Livewire::test(CreatePartInquiry::class)
            ->fillForm([
                'oem_number' => '11427566327',
                'email' => 'phone-caller@example.com',
                'phone' => '+370 600 12345',
                'quantity' => 2,
                'urgency' => 'urgent',
                'manufacturer' => 'BMW',
                'car_model' => '3 Series',
                'year' => '2018',
                'notes' => 'Customer called in, needs the part urgently.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $inquiry = PartInquiry::where('oem_number', '11427566327')->first();
        $this->assertNotNull($inquiry, 'Part inquiry should have been created');
        $this->assertSame('phone-caller@example.com', $inquiry->email);
        $this->assertSame('urgent', $inquiry->urgency->value ?? $inquiry->urgency);
        $this->assertNotNull($inquiry->ip_address);
    }

    /**
     * There's no separate Edit page for this resource — status transitions
     * happen via dedicated header actions on the View page's infolist, not
     * a shared form(). Confirms the form() changes above (which apply to
     * every $record, including a real one, since Filament's ViewRecord page
     * doesn't call form() but a future Edit page reusing it would) don't
     * interfere with that real, already-working mechanism.
     */
    #[Test]
    public function status_transition_actions_on_the_view_page_still_work(): void
    {
        $inquiry = PartInquiry::create([
            'email' => 'original@example.com', 'oem_number' => 'X1',
            'quantity' => 1, 'urgency' => 'normal', 'status' => 'new',
            'ip_address' => '127.0.0.1',
        ]);

        Livewire::test(ViewPartInquiry::class, ['record' => $inquiry->id])
            ->callAction('mark_sourced');

        $this->assertSame('sourced', $inquiry->fresh()->status->value);
        $this->assertSame('original@example.com', $inquiry->fresh()->email);
        $this->assertSame('X1', $inquiry->fresh()->oem_number);
    }
}
