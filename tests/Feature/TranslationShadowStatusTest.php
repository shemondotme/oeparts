<?php

namespace Tests\Feature;

use App\Enums\SettingType;
use App\Filament\Resources\TranslationResource;
use App\Filament\Resources\TranslationResource\Pages\EditTranslation;
use App\Filament\Resources\TranslationResource\Pages\ListTranslations;
use App\Models\Admin;
use App\Models\LanguageString;
use App\Models\Setting;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 7.3 — the "shadow trap": ui_copy() checks settings_trans('ui.'.$key)
 * BEFORE ever falling back to __() (which reads language_strings), so an
 * admin editing a language_strings row whose group/key is ALSO seeded under
 * ui.* sees zero visible effect. TranslationResource::isShadowed() surfaces
 * that instead of leaving it silent. See
 * memory/project_ui_copy_text_override_gap.md for the full history.
 */
class TranslationShadowStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    private function superAdmin(): Admin
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function setUiOverride(string $key, array $values): void
    {
        Setting::updateOrCreate(
            ['group' => 'ui', 'key' => $key],
            ['value' => json_encode($values), 'type' => SettingType::Json->value, 'is_encrypted' => false]
        );
    }

    #[Test]
    public function a_row_is_shadowed_when_a_matching_ui_override_has_a_non_empty_value_for_that_locale(): void
    {
        $this->setUiOverride('nav_cart_label', ['en' => 'My Cart']);
        $string = LanguageString::create(['lang_code' => 'en', 'group' => 'navbar', 'key' => 'cart_label', 'value' => 'Cart']);

        $this->assertTrue(TranslationResource::isShadowed($string));
    }

    #[Test]
    public function a_row_is_not_shadowed_when_no_matching_ui_override_row_exists(): void
    {
        $string = LanguageString::create(['lang_code' => 'en', 'group' => 'navbar', 'key' => 'totally_unseeded_key', 'value' => 'Whatever']);

        $this->assertFalse(TranslationResource::isShadowed($string));
    }

    #[Test]
    public function a_row_is_not_shadowed_when_the_matching_ui_override_is_empty_for_that_locale(): void
    {
        // The row exists (seeded prefix present) but has no DE text — an
        // empty override does not shadow, since ui_copy() itself falls
        // through to __() when the resolved string is blank.
        $this->setUiOverride('nav_cart_label', ['en' => 'My Cart', 'de' => '']);
        $string = LanguageString::create(['lang_code' => 'de', 'group' => 'navbar', 'key' => 'cart_label', 'value' => 'Warenkorb']);

        $this->assertFalse(TranslationResource::isShadowed($string));
    }

    #[Test]
    public function a_row_is_not_shadowed_when_the_override_exists_for_a_different_locale_only(): void
    {
        $this->setUiOverride('nav_cart_label', ['en' => 'My Cart']);
        $string = LanguageString::create(['lang_code' => 'de', 'group' => 'navbar', 'key' => 'cart_label', 'value' => 'Warenkorb']);

        $this->assertFalse(TranslationResource::isShadowed($string));
    }

    #[Test]
    public function a_group_outside_the_shadowing_prefix_map_is_never_shadowed(): void
    {
        // 'auth' has no ui.* prefix mapping at all — UiCopyInstaller never
        // seeded anything from lang/en/auth.php, so this can't shadow no
        // matter what ui.* rows happen to exist.
        $this->setUiOverride('auth_welcome_back', ['en' => 'Welcome back override']);
        $string = LanguageString::create(['lang_code' => 'en', 'group' => 'auth', 'key' => 'welcome_back', 'value' => 'Welcome back']);

        $this->assertFalse(TranslationResource::isShadowed($string));
    }

    #[Test]
    public function the_translations_table_shows_the_warning_icon_only_for_shadowed_rows(): void
    {
        $this->setUiOverride('search_no_results', ['en' => 'No matches found']);
        $shadowed = LanguageString::create(['lang_code' => 'en', 'group' => 'search', 'key' => 'no_results', 'value' => 'No results found.']);
        $notShadowed = LanguageString::create(['lang_code' => 'en', 'group' => 'auth', 'key' => 'welcome_back', 'value' => 'Welcome back']);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(ListTranslations::class)
            ->set('tableRecordsPerPage', 500)
            ->assertTableColumnStateSet('shadowed', true, $shadowed)
            ->assertTableColumnStateSet('shadowed', false, $notShadowed);
    }

    #[Test]
    public function the_view_page_shows_the_storefront_status_section_only_when_shadowed(): void
    {
        $this->setUiOverride('search_no_results', ['en' => 'No matches found']);
        $shadowed = LanguageString::create(['lang_code' => 'en', 'group' => 'search', 'key' => 'no_results', 'value' => 'No results found.']);
        $notShadowed = LanguageString::create(['lang_code' => 'en', 'group' => 'auth', 'key' => 'welcome_back', 'value' => 'Welcome back']);
        $this->actingAs($this->superAdmin(), 'admin');

        $this->get(TranslationResource::getUrl('view', ['record' => $shadowed]))
            ->assertSuccessful()
            ->assertSee('Shadowed by a Site Copy Library override');

        $this->get(TranslationResource::getUrl('view', ['record' => $notShadowed]))
            ->assertSuccessful()
            ->assertDontSee('Shadowed by a Site Copy Library override');
    }

    #[Test]
    public function editing_a_shadowed_row_still_saves_correctly(): void
    {
        // This phase must only surface visibility, never change save
        // behavior — a shadowed row is still a real, editable translation
        // (e.g. for other locales the ui.* override doesn't cover, or for
        // when the override is later cleared).
        $this->setUiOverride('search_no_results', ['en' => 'No matches found']);
        $string = LanguageString::create(['lang_code' => 'en', 'group' => 'search', 'key' => 'no_results', 'value' => 'No results found.']);
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(EditTranslation::class, ['record' => $string->getKey()])
            ->fillForm(['value' => 'Updated translation text'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Updated translation text', $string->fresh()->value);
    }
}
