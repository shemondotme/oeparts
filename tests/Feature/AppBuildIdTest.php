<?php

namespace Tests\Feature;

use App\Support\AppBuildId;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppBuildIdTest extends TestCase
{
    #[Test]
    public function current_returns_a_non_empty_string(): void
    {
        $this->assertNotEmpty(AppBuildId::current());
    }

    #[Test]
    public function current_contains_the_version_json_version(): void
    {
        $version = json_decode(file_get_contents(base_path('version.json')), true)['version'] ?? null;

        $this->assertNotNull($version);
        $this->assertStringStartsWith($version.'+', AppBuildId::current());
    }

    #[Test]
    public function current_is_stable_across_repeated_calls(): void
    {
        $this->assertSame(AppBuildId::current(), AppBuildId::current());
    }
}
