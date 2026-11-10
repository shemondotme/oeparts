<?php

namespace Tests\Feature;

use App\Filament\Resources\RedirectResource\Pages\CreateRedirect;
use App\Models\Admin;
use App\Models\Redirect;
use Database\Seeders\AdminSeeder;
use Database\Seeders\LanguagesSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The Redirects form accepts pattern rules and 410s, and refuses the ones that cannot work. */
class PatternRedirectsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, LanguagesSeeder::class, RolesSeeder::class, AdminSeeder::class]);
        $this->actingAs(Admin::where('email', 'superadmin@oeparts.test')->firstOrFail(), 'admin');
    }

    public function test_a_wildcard_rule_is_saved(): void
    {
        Livewire::test(CreateRedirect::class)
            ->fillForm(['match_type' => 'wildcard', 'from_url' => 'en/old-shop/*', 'to_url' => '/en/parts/$1', 'type' => '301', 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('redirects', ['from_url' => 'en/old-shop/*', 'to_url' => '/en/parts/$1', 'match_type' => 'wildcard']);
    }

    public function test_a_rule_that_would_redirect_to_a_url_it_matches_again_is_refused(): void
    {
        Livewire::test(CreateRedirect::class)
            ->fillForm(['match_type' => 'wildcard', 'from_url' => 'en/*', 'to_url' => '/en/new/$1', 'type' => '301', 'is_active' => true])
            ->call('create')
            ->assertHasFormErrors(['to_url']);

        $this->assertSame(0, Redirect::count());
    }

    public function test_an_invalid_regular_expression_is_refused(): void
    {
        Livewire::test(CreateRedirect::class)
            ->fillForm(['match_type' => 'regex', 'from_url' => 'en/([unclosed', 'to_url' => '/en/x', 'type' => '301', 'is_active' => true])
            ->call('create')
            ->assertHasFormErrors(['from_url']);
    }

    public function test_a_410_rule_needs_no_destination(): void
    {
        Livewire::test(CreateRedirect::class)
            ->fillForm(['match_type' => 'exact', 'from_url' => 'en/discontinued', 'type' => '410', 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('redirects', ['from_url' => 'en/discontinued', 'type' => '410', 'to_url' => '']);
    }

    public function test_a_301_still_requires_a_destination(): void
    {
        Livewire::test(CreateRedirect::class)
            ->fillForm(['match_type' => 'exact', 'from_url' => 'en/old', 'type' => '301', 'is_active' => true])
            ->call('create')
            ->assertHasFormErrors(['to_url' => 'required']);
    }
}
