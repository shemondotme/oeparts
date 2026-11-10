<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\SeoSetupWizard;
use App\Models\Admin;
use Database\Seeders\AdminSeeder;
use Database\Seeders\LanguagesSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The guided first-run page for the handful of settings that decide whether a new site gets found. */
class SeoSetupWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingsSeeder::class, LanguagesSeeder::class, RolesSeeder::class, AdminSeeder::class]);
        $this->actingAs(Admin::where('email', 'superadmin@oeparts.test')->firstOrFail(), 'admin');
    }

    public function test_it_opens_with_the_current_values(): void
    {
        Livewire::test(SeoSetupWizard::class)
            ->assertOk()
            ->assertSet('data.default_robots', 'index,follow')
            ->assertSet('data.thin_content_min_words', 100)
            ->assertSet('data.audit_alerts_enabled', true)
            ->assertSee('Your address');
    }

    public function test_finishing_saves_only_what_changed_and_goes_to_the_audit(): void
    {
        Livewire::test(SeoSetupWizard::class)
            ->fillForm([
                'canonical_host' => 'Shop.Example.org',
                'google_verification' => 'abc123',
                'thin_content_noindex' => true,
                'thin_content_min_words' => 150,
                'ai_crawler_tracking' => false,
            ])
            ->call('finish')
            ->assertHasNoFormErrors()
            ->assertRedirect(\App\Filament\Pages\Settings\SeoAudit::getUrl());

        $this->assertSame('shop.example.org', settings('seo.canonical_host'));
        $this->assertSame('abc123', settings('seo.google_verification'));
        $this->assertTrue(filter_var(settings('seo.thin_content_noindex'), FILTER_VALIDATE_BOOLEAN));
        $this->assertSame('150', (string) settings('seo.thin_content_min_words'));
        $this->assertFalse(filter_var(settings('seo.ai_crawler_tracking'), FILTER_VALIDATE_BOOLEAN));
        $this->assertSame('index,follow', settings('seo.default_robots'));
    }

    public function test_a_host_with_a_scheme_is_refused(): void
    {
        Livewire::test(SeoSetupWizard::class)
            ->fillForm(['canonical_host' => 'https://shop.example.org/'])
            ->call('finish')
            ->assertHasFormErrors(['canonical_host']);

        $this->assertSame('', (string) settings('seo.canonical_host'));
    }

    public function test_a_noindex_default_is_called_out_and_still_the_admins_choice(): void
    {
        Livewire::test(SeoSetupWizard::class)
            ->fillForm(['default_robots' => 'noindex,follow'])
            ->assertSee('stays out of search results');
    }
}
