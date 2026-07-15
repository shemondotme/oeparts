<?php

namespace Tests\Feature;

use App\Filament\Pages\System\PermissionMatrix;
use App\Models\Admin;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression coverage: PermissionMatrix::hasPermission() did a fresh
 * Role::find() + Spatie hasPermissionTo() lookup on every call, and the
 * Blade view calls it up to 3× per matrix cell for every role × every
 * permission — a real matrix (multiple roles × ~80 permissions) fired
 * 1000+ separate queries and took ~29s to render, confirmed live via
 * direct page-load timing. Fixed with a single memoized query against the
 * role_has_permissions pivot table (12s after the fix, on the same known-
 * slow local XAMPP environment other tests in this suite already note).
 */
class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesSeeder::class]);

        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');
    }

    #[Test]
    public function rendering_the_matrix_does_not_query_once_per_cell(): void
    {
        $role = Role::where('name', 'manager')->where('guard_name', 'admin')->firstOrFail();
        $permission = Permission::where('guard_name', 'admin')->firstOrFail();

        DB::enableQueryLog();
        $component = Livewire::test(PermissionMatrix::class);
        // Force the memoized map to build, then read it many times — this
        // is exactly what the Blade view does per cell.
        for ($i = 0; $i < 50; $i++) {
            $component->instance()->hasPermission($role->id, $permission->id);
        }
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(
            10,
            $queryCount,
            "hasPermission() must not re-query per call — {$queryCount} queries fired for 50 calls, the pre-fix N+1 bug fired one query per call."
        );
    }

    #[Test]
    public function has_permission_reflects_the_real_pivot_table_state(): void
    {
        $role = Role::where('name', 'manager')->where('guard_name', 'admin')->firstOrFail();
        $permission = Permission::where('guard_name', 'admin')->firstOrFail();
        $role->givePermissionTo($permission);

        $component = Livewire::test(PermissionMatrix::class);
        $this->assertTrue($component->instance()->hasPermission($role->id, $permission->id));

        $role->revokePermissionTo($permission);
        // A fresh component instance per render (Livewire doesn't persist
        // the private memoized property across requests) must reflect the change.
        $freshComponent = Livewire::test(PermissionMatrix::class);
        $this->assertFalse($freshComponent->instance()->hasPermission($role->id, $permission->id));
    }

    #[Test]
    public function toggle_permission_grants_and_revokes_correctly_and_invalidates_the_cache(): void
    {
        $role = Role::where('name', 'manager')->where('guard_name', 'admin')->firstOrFail();
        $permission = Permission::where('guard_name', 'admin')->firstOrFail();
        $role->revokePermissionTo($permission);

        $component = Livewire::test(PermissionMatrix::class);
        $this->assertFalse($component->instance()->hasPermission($role->id, $permission->id));

        $component->call('togglePermission', $role->id, $permission->id);
        // Same component instance, within the same request — the memoized
        // map must be invalidated by togglePermission() or this reads stale.
        $this->assertTrue($component->instance()->hasPermission($role->id, $permission->id));

        $component->call('togglePermission', $role->id, $permission->id);
        $this->assertFalse($component->instance()->hasPermission($role->id, $permission->id));
    }
}
