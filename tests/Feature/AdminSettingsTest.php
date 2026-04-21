<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
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

        // Create some test settings
        Setting::create([
            'group' => 'app',
            'key' => 'name',
            'value' => 'OEMHub',
            'type' => 'string',
            'description' => 'Application name',
        ]);

        Setting::create([
            'group' => 'app',
            'key' => 'version',
            'value' => '1.0.0',
            'type' => 'string',
            'description' => 'Application version',
        ]);

        Setting::create([
            'group' => 'mail',
            'key' => 'smtp_host',
            'value' => 'smtp.mailgun.org',
            'type' => 'string',
            'description' => 'SMTP host',
        ]);
    }

    #[Test]
    public function admin_can_view_settings_index(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.settings.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.settings.index');
        $response->assertSee('Settings');
        $response->assertSee('app');
        $response->assertSee('mail');
    }

    #[Test]
    public function admin_can_view_settings_edit_page(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.settings.edit', 'app'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.settings.edit');
        $response->assertSee('Edit App Settings');
        $response->assertSee('name');
        $response->assertSee('version');
    }

    #[Test]
    public function admin_can_update_settings(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->put(route('admin.settings.update', 'app'), [
            'settings' => [
                'name' => 'Updated OEMHub',
                'version' => '2.0.0',
            ]
        ]);

        $response->assertRedirect(route('admin.settings.edit', 'app'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('settings', [
            'group' => 'app',
            'key' => 'name',
            'value' => 'Updated OEMHub',
        ]);

        $this->assertDatabaseHas('settings', [
            'group' => 'app',
            'key' => 'version',
            'value' => '2.0.0',
        ]);
    }

    #[Test]
    public function admin_can_view_create_setting_page(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.settings.create'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.settings.create');
        $response->assertSee('Create New Setting');
    }

    #[Test]
    public function admin_can_create_new_setting(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->post(route('admin.settings.store'), [
            'group' => 'new_group',
            'key' => 'test_key',
            'value' => 'test_value',
            'type' => 'string',
            'description' => 'Test description',
        ]);

        $response->assertRedirect(route('admin.settings.edit', 'new_group'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('settings', [
            'group' => 'new_group',
            'key' => 'test_key',
            'value' => 'test_value',
            'type' => 'string',
        ]);
    }

    #[Test]
    public function admin_can_delete_setting(): void
    {
        $this->actingAs($this->admin, 'admin');

        // Create a setting to delete
        $setting = Setting::create([
            'group' => 'test',
            'key' => 'to_delete',
            'value' => 'value',
            'type' => 'string',
        ]);

        $response = $this->delete(route('admin.settings.destroy', [
            'group' => 'test',
            'key' => 'to_delete',
        ]));

        $response->assertRedirect(route('admin.settings.edit', 'test'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('settings', [
            'group' => 'test',
            'key' => 'to_delete',
        ]);
    }

    #[Test]
    public function settings_validation_works(): void
    {
        $this->actingAs($this->admin, 'admin');

        // Test required fields
        $response = $this->post(route('admin.settings.store'), [
            'group' => '',
            'key' => '',
            'value' => '',
        ]);

        $response->assertSessionHasErrors(['group', 'key', 'type']);

        // Test invalid type
        $response = $this->post(route('admin.settings.store'), [
            'group' => 'test',
            'key' => 'test',
            'value' => 'test',
            'type' => 'invalid_type',
        ]);

        $response->assertSessionHasErrors(['type']);
    }

    #[Test]
    public function encrypted_settings_are_properly_handled(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->post(route('admin.settings.store'), [
            'group' => 'security',
            'key' => 'api_key',
            'value' => 'secret-api-key-123',
            'type' => 'encrypted',
            'description' => 'Encrypted API key',
        ]);

        $response->assertRedirect(route('admin.settings.edit', 'security'));
        
        // Check that the value is encrypted in database
        $setting = Setting::where('group', 'security')->where('key', 'api_key')->first();
        $this->assertNotNull($setting);
        $this->assertNotEquals('secret-api-key-123', $setting->value);
        $this->assertStringContainsString('base64:', $setting->value);
    }

    #[Test]
    public function json_settings_are_properly_validated(): void
    {
        $this->actingAs($this->admin, 'admin');

        // Valid JSON
        $response = $this->post(route('admin.settings.store'), [
            'group' => 'config',
            'key' => 'json_config',
            'value' => '{"key": "value", "enabled": true}',
            'type' => 'json',
        ]);

        $response->assertSessionDoesntHaveErrors();

        // Invalid JSON
        $response = $this->post(route('admin.settings.store'), [
            'group' => 'config',
            'key' => 'invalid_json',
            'value' => '{invalid json}',
            'type' => 'json',
        ]);

        $response->assertSessionHasErrors(['value']);
    }
}