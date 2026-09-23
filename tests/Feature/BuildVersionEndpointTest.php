<?php

namespace Tests\Feature;

use App\Support\AppBuildId;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BuildVersionEndpointTest extends TestCase
{
    #[Test]
    public function build_version_endpoint_returns_200_with_build_key(): void
    {
        $response = $this->getJson('/build-version');

        $response->assertStatus(200);
        $response->assertJsonStructure(['build']);
    }

    #[Test]
    public function build_version_endpoint_matches_app_build_id(): void
    {
        $response = $this->getJson('/build-version');

        $response->assertJson(['build' => AppBuildId::current()]);
    }

    #[Test]
    public function build_version_endpoint_is_publicly_accessible_without_auth(): void
    {
        $response = $this->getJson('/build-version');

        $response->assertStatus(200);
    }
}
