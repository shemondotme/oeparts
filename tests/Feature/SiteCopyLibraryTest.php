<?php

namespace Tests\Feature;

use App\Enums\SettingType;
use App\Filament\Pages\Settings\SiteCopyLibrary;
use App\Models\Admin;
use App\Models\Setting;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 7.2 — browsable/editable tool for the ~344 ui.* cart_/search_/nav_
 * text-override rows that previously had zero admin UI. See
 * memory/project_ui_copy_text_override_gap.md for the full history.
 */
class SiteCopyLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    private function superAdmin(): Admin
    {
        // is_active must be explicit — Admin::canAccessPanel() 403s real
        // HTTP requests otherwise. See SettingsSearchTest for the full note.
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    /**
     * A migration (install_ui_copy_for_search_cart_nav) already runs
     * UiCopyInstaller against every fresh test database, seeding real
     * cart_/search_/nav_ rows — updateOrCreate() so this fixture works
     * whether the key already exists (the common case) or not.
     */
    private function makeUiRow(string $key, array $values = ['en' => 'English text']): Setting
    {
        return Setting::updateOrCreate(
            ['group' => 'ui', 'key' => $key],
            [
                'value' => json_encode($values),
                'type' => SettingType::Json->value,
                'is_encrypted' => false,
            ]
        );
    }

    #[Test]
    public function a_super_admin_can_open_the_page(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        $this->get(SiteCopyLibrary::getUrl())
            ->assertSuccessful()
            ->assertSee('Site Copy Library');
    }

    #[Test]
    public function a_non_admin_role_is_forbidden(): void
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole('support');
        $this->actingAs($admin, 'admin');

        $this->get(SiteCopyLibrary::getUrl())->assertForbidden();
    }

    #[Test]
    public function only_cart_search_and_nav_prefixed_rows_appear_defensively_excluding_every_other_prefix(): void
    {
        // Defensive per the plan: even though nothing seeds these other
        // prefixes today, the query must not accidentally widen to them —
        // hero_* stays on CustomizationSettings' own tab, and
        // checkout_/account_/footer_ are an explicit, unbuilt backlog item.
        $cart = $this->makeUiRow('cart_empty_message');
        $search = $this->makeUiRow('search_no_results');
        $nav = $this->makeUiRow('nav_cart_label');
        $hero = $this->makeUiRow('hero_title');
        $checkout = $this->makeUiRow('checkout_thank_you');
        $account = $this->makeUiRow('account_welcome');
        $footer = $this->makeUiRow('footer_copyright');

        $this->actingAs($this->superAdmin(), 'admin');

        // The real ui.* table already has 344 cart_/search_/nav_ rows
        // (seeded by the install_ui_copy_for_search_cart_nav migration) —
        // bump the page size so these specific fixture rows aren't pushed
        // past the default 10-per-page cut by alphabetical sort.
        Livewire::test(SiteCopyLibrary::class)
            ->set('tableRecordsPerPage', 500)
            ->assertCanSeeTableRecords([$cart, $search, $nav])
            ->assertCanNotSeeTableRecords([$hero, $checkout, $account, $footer]);
    }

    #[Test]
    public function the_category_filter_narrows_to_just_that_prefix(): void
    {
        $cart = $this->makeUiRow('cart_empty_message');
        $search = $this->makeUiRow('search_no_results');
        $nav = $this->makeUiRow('nav_cart_label');

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SiteCopyLibrary::class)
            ->set('tableRecordsPerPage', 500)
            ->filterTable('prefix', 'cart_')
            ->assertCanSeeTableRecords([$cart])
            ->assertCanNotSeeTableRecords([$search, $nav]);
    }

    #[Test]
    public function editing_a_row_saves_all_locales_and_busts_the_ui_settings_cache(): void
    {
        $row = $this->makeUiRow('cart_empty_message', ['en' => 'Old text']);

        // Prime the cache the same way ui_copy() would on a real request.
        $this->assertSame('Old text', settings_trans('ui.cart_empty_message'));

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SiteCopyLibrary::class)
            ->callTableAction('edit', $row, data: [
                'value' => [
                    'en' => 'New text',
                    'de' => 'Neuer Text',
                    'lt' => 'Naujas tekstas',
                    'fr' => 'Nouveau texte',
                    'es' => 'Texto nuevo',
                ],
            ]);

        $row->refresh();
        $decoded = json_decode($row->value, true);
        $this->assertSame('New text', $decoded['en']);
        $this->assertSame('Neuer Text', $decoded['de']);

        // Cache-busting: SettingsService::forget('ui') must have run, or a
        // stale cached read would still return 'Old text' here.
        $this->assertSame('New text', settings_trans('ui.cart_empty_message'));
    }

    #[Test]
    public function editing_a_row_immediately_changes_what_ui_copy_renders(): void
    {
        $this->makeUiRow('cart_empty_message', ['en' => 'Old cart text']);
        $this->actingAs($this->superAdmin(), 'admin');

        $row = Setting::where('group', 'ui')->where('key', 'cart_empty_message')->sole();

        Livewire::test(SiteCopyLibrary::class)
            ->callTableAction('edit', $row, data: [
                'value' => [
                    'en' => 'Brand new cart text',
                    'de' => '',
                    'lt' => '',
                    'fr' => '',
                    'es' => '',
                ],
            ]);

        $this->assertSame(
            'Brand new cart text',
            ui_copy('cart_empty_message', 'cart.empty_message')
        );
    }
}
