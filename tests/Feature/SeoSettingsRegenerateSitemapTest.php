<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\SeoSettings;
use App\Jobs\RegenerateSitemap;
use App\Models\Admin;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "Regenerate sitemap now" used to call SitemapService::generateAll()
 * inline in the Livewire request — fine for a demo catalog, but at ~100k
 * products (500k+ <loc> entries once split across locales) it risks running
 * past the web request's timeout. It now dispatches RegenerateSitemap
 * instead, which defers the work on any install with a real queue worker
 * and is a no-op behavior change on QUEUE_CONNECTION=sync (still runs
 * inline, same as before — see RegenerateSitemap's docstring).
 */
class SeoSettingsRegenerateSitemapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesSeeder::class, SettingsSeeder::class]);
    }

    #[Test]
    public function regenerate_action_dispatches_a_job_instead_of_running_inline(): void
    {
        Queue::fake();

        $admin = Admin::factory()->create(['name' => 'Jane Admin']);
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        Livewire::test(SeoSettings::class)
            ->assertActionExists('regenerateSitemap')
            ->callAction('regenerateSitemap');

        Queue::assertPushed(RegenerateSitemap::class, fn (RegenerateSitemap $job) => $job->triggeredBy === 'Jane Admin');
    }
}
