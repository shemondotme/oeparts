<?php

namespace Tests\Feature;

use App\Providers\InstanceServiceProvider;
use App\Providers\MigrationServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A zip self-update replaces only the paths its INSTALLED release lists in updates.core_paths. A provider that is named only in
 * bootstrap/providers.php therefore never reaches a site that updates from an older release whose list lacked that file: its
 * safe-mode and migration services would silently not exist (found by the 2.2.0 update rehearsal).
 */
class ProvidersSurviveAZipUpdateTest extends TestCase
{
    #[Test]
    public function the_instance_and_migration_providers_load_from_a_provider_that_a_zip_update_does_ship(): void
    {
        // AppServiceProvider lives under app/, which every release swaps. It must register them itself.
        $source = (string) file_get_contents(app_path('Providers/AppServiceProvider.php'));

        $this->assertStringContainsString('register(InstanceServiceProvider::class)', $source);
        $this->assertStringContainsString('register(MigrationServiceProvider::class)', $source);

        // And they are loaded exactly once even though bootstrap/providers.php also names them.
        $this->assertNotNull($this->app->getProvider(InstanceServiceProvider::class));
        $this->assertNotNull($this->app->getProvider(MigrationServiceProvider::class));
        $this->assertCount(1, array_filter(
            array_keys($this->app->getLoadedProviders()),
            static fn (string $class): bool => $class === InstanceServiceProvider::class,
        ));
    }

    #[Test]
    public function every_provider_in_the_providers_file_is_either_registered_by_the_app_provider_or_the_file_itself_ships(): void
    {
        $shipped = (array) config('updates.core_paths');

        // The permanent fix for future releases: the file is part of what an update swaps.
        $this->assertContains('bootstrap/providers.php', $shipped);

        // And for the releases that updated FROM a list without it: nothing outside app/ may be the only place a provider
        // added since 2.1.0 is named. The two that were, are registered by AppServiceProvider (checked above).
        $sinceTwoOneZero = [InstanceServiceProvider::class, MigrationServiceProvider::class];
        $appProvider = (string) file_get_contents(app_path('Providers/AppServiceProvider.php'));

        foreach ($sinceTwoOneZero as $provider) {
            $this->assertStringContainsString('register('.class_basename($provider).'::class)', $appProvider, $provider.' must not depend on bootstrap/providers.php alone');
        }
    }
}
