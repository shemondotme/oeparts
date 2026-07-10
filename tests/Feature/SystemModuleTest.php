<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Language;
use App\Policies\AdminPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemModuleTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\LanguagesSeeder::class,
            \Database\Seeders\RolesSeeder::class,
            \Database\Seeders\AdminSeeder::class,
        ]);

        $this->superAdmin = Admin::where('email', 'superadmin@oeparts.test')->firstOrFail();
        $this->actingAs($this->superAdmin, 'admin');
    }

    public function test_activity_log_view_page_renders(): void
    {
        // Regression: wrong TextEntry namespace (Schemas vs Infolists) 500'd
        // every audit-trail detail view.
        $log = ActivityLog::create([
            'admin_id' => $this->superAdmin->id,
            'action' => 'test_action',
            'model_type' => Admin::class,
            'model_id' => $this->superAdmin->id,
            'old_values' => [],
            'new_values' => ['x' => 1],
            'ip_address' => '127.0.0.1',
        ]);

        Livewire::test(\App\Filament\Resources\ActivityLogResource\Pages\ViewActivityLog::class, ['record' => $log->id])
            ->assertOk();
    }

    public function test_admin_cannot_delete_self_or_last_active_super_admin(): void
    {
        // Policy path — MUST act as the limited admin: Gate::before grants
        // everything whenever the AUTHENTICATED admin is a super_admin,
        // regardless of the subject user.
        $manager = Admin::where('email', 'manager@oeparts.test')->firstOrFail();
        $manager->givePermissionTo('delete admins');
        $this->actingAs($manager, 'admin');

        $this->assertFalse($manager->can('delete', $manager), 'self-delete must be denied');
        $this->assertFalse($manager->can('delete', $this->superAdmin), 'deleting the last active super admin must be denied');

        // UI path (super_admin actor — Gate::before bypasses policies, the
        // hidden() closures must still protect):
        $this->actingAs($this->superAdmin, 'admin');
        Livewire::test(\App\Filament\Resources\AdminResource\Pages\ListAdmins::class)
            ->loadTable()
            ->assertTableActionHidden('delete', $this->superAdmin);
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        Livewire::test(\App\Filament\Resources\AdminResource\Pages\EditAdmin::class, ['record' => $this->superAdmin->id])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasFormErrors(['is_active']);

        $this->assertTrue($this->superAdmin->fresh()->is_active);
    }

    public function test_super_admin_role_is_immutable_and_in_use_roles_undeletable(): void
    {
        $superRole = Role::findByName('super_admin', 'admin');
        $managerRole = Role::findByName('manager', 'admin');

        // Policy path — act as the limited admin (see Gate::before note above):
        $manager = Admin::where('email', 'manager@oeparts.test')->firstOrFail();
        $manager->givePermissionTo(['edit roles', 'delete roles']);
        $this->actingAs($manager, 'admin');

        $this->assertFalse($manager->can('update', $superRole));
        $this->assertFalse($manager->can('delete', $superRole));
        $this->assertFalse($manager->can('delete', $managerRole), 'manager role is held by an admin — in-use roles must be undeletable');

        // UI path (super_admin actor). NOTE: assertTableActionHidden() is
        // unreliable for link-type EditActions (reports visible while the
        // action's own isHidden() is true) — assert on the action directly.
        $this->actingAs($this->superAdmin, 'admin');
        $list = Livewire::test(\App\Filament\Resources\RoleResource\Pages\ListRoles::class)
            ->loadTable()
            ->assertTableActionHidden('delete', $superRole)
            ->assertTableActionHidden('delete', $managerRole);

        $editAction = $list->instance()->getTable()->getAction('edit');
        $editAction->record($superRole);
        $this->assertTrue($editAction->isHidden(), 'edit must be hidden for the super_admin role');
    }

    public function test_default_and_english_languages_are_undeletable(): void
    {
        $en = Language::where('code', 'en')->firstOrFail();

        $manager = Admin::where('email', 'manager@oeparts.test')->firstOrFail();
        $manager->givePermissionTo('delete languages');
        $this->actingAs($manager, 'admin');
        $this->assertFalse($manager->can('delete', $en));

        $this->actingAs($this->superAdmin, 'admin');
        Livewire::test(\App\Filament\Resources\LanguageResource\Pages\ListLanguages::class)
            ->loadTable()
            ->assertTableActionHidden('delete', $en);
    }

    public function test_setting_a_default_language_unsets_the_previous_default(): void
    {
        $en = Language::where('code', 'en')->firstOrFail();
        $de = Language::where('code', 'de')->firstOrFail();
        $en->update(['is_default' => true]);

        $de->update(['is_default' => true]);

        $this->assertTrue($de->fresh()->is_default);
        $this->assertFalse($en->fresh()->is_default, 'two default languages must be impossible');
    }
}
