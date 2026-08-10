<?php

namespace Tests\Feature;

use App\Enums\RedirectType;
use App\Filament\Resources\NotFoundLogResource\Pages\ListNotFoundLogs;
use App\Filament\Resources\RedirectResource\Pages\CreateRedirect;
use App\Filament\Resources\RedirectResource\Pages\EditRedirect;
use App\Models\Admin;
use App\Models\NotFoundLog;
use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * RedirectResource's form placeholder told admins to enter a leading slash
 * ("/old-page"), but HandleRedirects matches against Request::path(), which
 * never has one — a redirect saved by following that exact guidance never
 * matched. Also fixed: no uniqueness validation (raw DB error on duplicate),
 * and no guard against a self-redirect or a two-hop A→B/B→A loop.
 */
class RedirectResourceTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesSeeder::class);
        // RolesSeeder never grants 'view/create/edit redirects' to any role
        // (not even 'admin') — redirects management is currently reachable
        // only via the super_admin Gate::before bypass. Unrelated to the
        // bugs under test here, so not addressed in this change; acting as
        // super_admin reflects the actual current access model.
        $this->admin = Admin::factory()->create();
        $this->admin->assignRole('super_admin');
        $this->actingAs($this->admin, 'admin');
    }

    #[Test]
    public function saving_strips_a_leading_slash_and_lowercases_from_url(): void
    {
        Livewire::test(CreateRedirect::class)
            ->fillForm(['from_url' => '/Old-Page', 'to_url' => '/new-page', 'type' => RedirectType::Permanent->value, 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('redirects', ['from_url' => 'old-page']);
        $this->assertDatabaseMissing('redirects', ['from_url' => '/Old-Page']);
    }

    #[Test]
    public function duplicate_from_url_is_rejected_with_a_form_error_not_a_raw_db_crash(): void
    {
        Redirect::create(['from_url' => 'existing-page', 'to_url' => '/somewhere', 'type' => RedirectType::Permanent, 'is_active' => true]);

        Livewire::test(CreateRedirect::class)
            ->fillForm(['from_url' => 'existing-page', 'to_url' => '/elsewhere', 'type' => RedirectType::Permanent->value, 'is_active' => true])
            ->call('create')
            ->assertHasFormErrors(['from_url']);
    }

    #[Test]
    public function a_redirect_to_itself_is_rejected(): void
    {
        Livewire::test(CreateRedirect::class)
            ->fillForm(['from_url' => 'loop-page', 'to_url' => 'loop-page', 'type' => RedirectType::Permanent->value, 'is_active' => true])
            ->call('create')
            ->assertHasFormErrors(['to_url']);
    }

    #[Test]
    public function a_two_hop_redirect_loop_is_rejected(): void
    {
        Redirect::create(['from_url' => 'page-a', 'to_url' => 'page-b', 'type' => RedirectType::Permanent, 'is_active' => true]);

        Livewire::test(CreateRedirect::class)
            ->fillForm(['from_url' => 'page-b', 'to_url' => 'page-a', 'type' => RedirectType::Permanent->value, 'is_active' => true])
            ->call('create')
            ->assertHasFormErrors(['to_url']);
    }

    #[Test]
    public function editing_a_redirect_without_changing_to_url_is_not_rejected_as_its_own_reverse_loop(): void
    {
        $redirect = Redirect::create(['from_url' => 'page-c', 'to_url' => 'page-d', 'type' => RedirectType::Permanent, 'is_active' => true]);

        Livewire::test(EditRedirect::class, ['record' => $redirect->id])
            ->fillForm(['from_url' => 'page-c', 'to_url' => 'page-d', 'type' => RedirectType::Permanent->value, 'is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    #[Test]
    public function creating_a_redirect_from_a_404_log_that_already_has_one_shows_a_friendly_warning_not_a_crash(): void
    {
        Redirect::create(['from_url' => 'dead-link', 'to_url' => '/somewhere', 'type' => RedirectType::Permanent, 'is_active' => true]);
        $log = NotFoundLog::create([
            'path' => 'dead-link', 'path_hash' => hash('sha256', 'dead-link'),
            'lang' => 'en', 'hit_count' => 3, 'resolved' => false,
            'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);

        Livewire::test(ListNotFoundLogs::class)
            ->callTableAction('createRedirect', $log, data: ['to_url' => '/another-target', 'type' => RedirectType::Permanent->value]);

        $this->assertSame(1, Redirect::where('from_url', 'dead-link')->count());
        $this->assertFalse($log->fresh()->resolved);
    }
}
