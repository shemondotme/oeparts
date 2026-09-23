<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Models\Admin;
use App\Support\AppBuildId;
use Database\Seeders\AdminSeeder;
use Database\Seeders\LanguagesSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Exceptions\LivewireReleaseTokenMismatchException;
use Livewire\Features\SupportReleaseTokens\ReleaseToken;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Exercises Livewire's own release-token mechanism (config/AppServiceProvider,
 * resources/views/filament/hooks/build-freshness-check.blade.php) rather than
 * mocking it — a stale admin tab's next Livewire request must fail with the
 * library's real LivewireReleaseTokenMismatchException (surfaces as HTTP 419).
 */
class LivewireReleaseTokenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function livewire_release_token_defaults_to_the_current_app_build_id(): void
    {
        $this->assertSame(AppBuildId::current(), config('livewire.release_token'));
    }

    #[Test]
    public function release_token_verify_passes_for_a_freshly_generated_snapshot(): void
    {
        $this->seed([
            SettingsSeeder::class,
            LanguagesSeeder::class,
            RolesSeeder::class,
            AdminSeeder::class,
        ]);
        $admin = Admin::where('email', 'superadmin@oeparts.test')->firstOrFail();
        $this->actingAs($admin, 'admin');

        $instance = Livewire::test(Dashboard::class)->instance();

        $snapshot = [
            'memo' => [
                'name' => $instance->getName(),
                'release' => ReleaseToken::generate(get_class($instance)),
            ],
        ];

        // No exception thrown = pass.
        ReleaseToken::verify($snapshot);
        $this->assertTrue(true);
    }

    #[Test]
    public function release_token_verify_throws_once_the_deployed_build_changes(): void
    {
        $this->seed([
            SettingsSeeder::class,
            LanguagesSeeder::class,
            RolesSeeder::class,
            AdminSeeder::class,
        ]);
        $admin = Admin::where('email', 'superadmin@oeparts.test')->firstOrFail();
        $this->actingAs($admin, 'admin');

        $instance = Livewire::test(Dashboard::class)->instance();

        $staleSnapshot = [
            'memo' => [
                'name' => $instance->getName(),
                'release' => ReleaseToken::generate(get_class($instance)),
            ],
        ];

        // Simulate a deploy happening while this tab was open.
        config(['livewire.release_token' => 'a-different-build']);

        $this->expectException(LivewireReleaseTokenMismatchException::class);
        ReleaseToken::verify($staleSnapshot);
    }
}
