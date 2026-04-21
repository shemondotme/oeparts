<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Language;
use App\Models\LanguageString;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminTranslationsTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test admin
        $this->admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);

        // Create test languages
        Language::create([
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English',
            'locale' => 'en_US',
            'flag_emoji' => '🇺🇸',
            'is_default' => true,
            'is_active' => true,
        ]);

        Language::create([
            'code' => 'de',
            'name' => 'German',
            'native_name' => 'Deutsch',
            'locale' => 'de_DE',
            'flag_emoji' => '🇩🇪',
            'is_default' => false,
            'is_active' => true,
        ]);

        // Create test translation strings
        LanguageString::create([
            'lang_code' => 'en',
            'group' => 'auth',
            'key' => 'login',
            'value' => 'Login',
            'description' => 'Login button text',
        ]);

        LanguageString::create([
            'lang_code' => 'de',
            'group' => 'auth',
            'key' => 'login',
            'value' => 'Anmelden',
            'description' => 'Login button text',
        ]);

        LanguageString::create([
            'lang_code' => 'en',
            'group' => 'auth',
            'key' => 'register',
            'value' => 'Register',
            'description' => 'Register button text',
        ]);

        LanguageString::create([
            'lang_code' => 'en',
            'group' => 'validation',
            'key' => 'required',
            'value' => 'This field is required',
            'description' => 'Validation message',
        ]);
    }

    #[Test]
    public function admin_can_view_translations_index(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.translations.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.translations.index');
        $response->assertSee('Translation Management');
        $response->assertSee('auth');
        $response->assertSee('validation');
    }

    #[Test]
    public function admin_can_view_translation_group(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.translations.group', 'auth'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.translations.group');
        $response->assertSee('Edit Translations: auth');
        $response->assertSee('login');
        $response->assertSee('register');
    }

    #[Test]
    public function admin_can_update_translation(): void
    {
        $this->actingAs($this->admin, 'admin');

        $translation = LanguageString::where('group', 'auth')
            ->where('key', 'login')
            ->where('lang_code', 'en')
            ->first();

        $response = $this->put(route('admin.translations.update', $translation->id), [
            'value' => 'Sign In',
        ]);

        $response->assertRedirect(route('admin.translations.group', 'auth'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('language_strings', [
            'id' => $translation->id,
            'value' => 'Sign In',
        ]);
    }

    #[Test]
    public function admin_can_bulk_update_translations(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->put(route('admin.translations.bulkUpdate', 'auth'), [
            'translations' => [
                'en' => [
                    'login' => ['value' => 'Sign In'],
                    'register' => ['value' => 'Sign Up'],
                ],
                'de' => [
                    'login' => ['value' => 'Anmeldung'],
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.translations.group', 'auth'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('language_strings', [
            'group' => 'auth',
            'key' => 'login',
            'lang_code' => 'en',
            'value' => 'Sign In',
        ]);

        $this->assertDatabaseHas('language_strings', [
            'group' => 'auth',
            'key' => 'register',
            'lang_code' => 'en',
            'value' => 'Sign Up',
        ]);
    }

    #[Test]
    public function admin_can_view_create_translation_page(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.translations.create'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.translations.create');
        $response->assertSee('Add New Translation String');
    }

    #[Test]
    public function admin_can_create_new_translation(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->post(route('admin.translations.store'), [
            'group' => 'messages',
            'key' => 'welcome',
            'description' => 'Welcome message',
            'translations' => [
                'en' => 'Welcome to our site',
                'de' => 'Willkommen auf unserer Seite',
            ],
        ]);

        $response->assertRedirect(route('admin.translations.group', 'messages'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('language_strings', [
            'group' => 'messages',
            'key' => 'welcome',
            'lang_code' => 'en',
            'value' => 'Welcome to our site',
        ]);

        $this->assertDatabaseHas('language_strings', [
            'group' => 'messages',
            'key' => 'welcome',
            'lang_code' => 'de',
            'value' => 'Willkommen auf unserer Seite',
        ]);
    }

    #[Test]
    public function admin_can_delete_translation(): void
    {
        $this->actingAs($this->admin, 'admin');

        $translation = LanguageString::where('group', 'validation')->first();

        $response = $this->delete(route('admin.translations.destroy', $translation->id));

        $response->assertRedirect(route('admin.translations.group', 'validation'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('language_strings', [
            'id' => $translation->id,
        ]);
    }

    #[Test]
    public function admin_can_view_languages_page(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.translations.languages'));

        $response->assertStatus(200);
        $response->assertSee('Manage Languages');
        $response->assertSee('English');
        $response->assertSee('German');
    }

    #[Test]
    public function admin_can_add_new_language(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->post(route('admin.translations.languages.add'), [
            'code' => 'fr',
            'name' => 'French',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.translations.languages'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('languages', [
            'code' => 'fr',
            'name' => 'French',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function admin_can_update_language(): void
    {
        $this->actingAs($this->admin, 'admin');

        $language = Language::where('code', 'de')->first();

        $response = $this->put(route('admin.translations.languages.update', $language->id), [
            'name' => 'Deutsch',
            'is_active' => false,
        ]);

        $response->assertRedirect(route('admin.translations.languages'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('languages', [
            'id' => $language->id,
            'name' => 'Deutsch',
            'is_active' => false,
        ]);
    }

    #[Test]
    public function admin_can_view_scan_page(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.translations.scan'));

        $response->assertStatus(200);
        $response->assertSee('Scan for Translation Strings');
    }

    #[Test]
    public function admin_can_view_import_page(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.translations.import'));

        $response->assertStatus(200);
        $response->assertSee('Import Translations');
    }

    #[Test]
    public function admin_can_export_translations(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.translations.export'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
    }

    #[Test]
    public function admin_can_export_specific_group(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.translations.export.group', 'auth'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
    }

    #[Test]
    public function translation_validation_works(): void
    {
        $this->actingAs($this->admin, 'admin');

        // Test required fields
        $response = $this->post(route('admin.translations.store'), [
            'group' => '',
            'key' => '',
            'translations' => [],
        ]);

        $response->assertSessionHasErrors(['group', 'key', 'translations.en']);

        // Test duplicate key in same group
        $response = $this->post(route('admin.translations.store'), [
            'group' => 'auth',
            'key' => 'login', // Already exists
            'translations' => ['en' => 'test'],
        ]);

        $response->assertSessionHasErrors(['key']);
    }

    #[Test]
    public function language_validation_works(): void
    {
        $this->actingAs($this->admin, 'admin');

        // Test required fields
        $response = $this->post(route('admin.translations.languages.add'), [
            'code' => '',
            'name' => '',
        ]);

        $response->assertSessionHasErrors(['code', 'name']);

        // Test duplicate language code
        $response = $this->post(route('admin.translations.languages.add'), [
            'code' => 'en', // Already exists
            'name' => 'English',
        ]);

        $response->assertSessionHasErrors(['code']);
    }

    #[Test]
    public function translations_require_admin_authentication(): void
    {
        // Test without authentication
        $response = $this->get(route('admin.translations.index'));
        $response->assertRedirect(route('admin.login'));

        $response = $this->get(route('admin.translations.group', 'auth'));
        $response->assertRedirect(route('admin.login'));

        $response = $this->get(route('admin.translations.create'));
        $response->assertRedirect(route('admin.login'));

        $response = $this->get(route('admin.translations.languages'));
        $response->assertRedirect(route('admin.login'));
    }

    #[Test]
    public function default_language_cannot_be_deactivated(): void
    {
        $this->actingAs($this->admin, 'admin');

        $language = Language::where('code', 'en')->first();

        $response = $this->put(route('admin.translations.languages.update', $language->id), [
            'name' => 'English',
            'is_active' => false, // Try to deactivate default language
        ]);

        $response->assertSessionHasErrors(['is_active']);
    }

    #[Test]
    public function only_one_default_language_allowed(): void
    {
        $this->actingAs($this->admin, 'admin');

        $language = Language::where('code', 'de')->first();

        // Try to set German as default
        $response = $this->put(route('admin.translations.languages.update', $language->id), [
            'name' => 'German',
            'is_default' => true,
        ]);

        // Should fail because English is already default
        $response->assertSessionHasErrors(['is_default']);
    }
}