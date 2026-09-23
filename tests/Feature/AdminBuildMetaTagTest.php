<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Support\AppBuildId;
use Database\Seeders\AdminSeeder;
use Database\Seeders\LanguagesSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminBuildMetaTagTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_page_includes_app_build_meta_tag(): void
    {
        $this->seed([
            SettingsSeeder::class,
            LanguagesSeeder::class,
            RolesSeeder::class,
            AdminSeeder::class,
        ]);
        $admin = Admin::where('email', 'superadmin@oeparts.test')->firstOrFail();
        $this->actingAs($admin, 'admin');

        $response = $this->get('/admin');

        $response->assertOk();
        $response->assertSee('name="app-build"', false);
        $response->assertSee('content="'.AppBuildId::current().'"', false);
    }
}
