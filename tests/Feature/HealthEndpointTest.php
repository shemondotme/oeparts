<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function health_endpoint_returns_200_when_healthy(): void
    {
        $response = $this->getJson('/health');

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }

    #[Test]
    public function health_endpoint_returns_expected_structure(): void
    {
        $response = $this->getJson('/health');

        $response->assertJsonStructure([
            'status',
            'version',
            'timestamp',
            'checks' => [
                'database',
                'cache',
            ],
        ]);
    }

    #[Test]
    public function health_endpoint_reports_database_ok(): void
    {
        $response = $this->getJson('/health');

        $response->assertJsonPath('checks.database', 'ok');
    }

    #[Test]
    public function health_endpoint_reports_cache_ok(): void
    {
        $response = $this->getJson('/health');

        $response->assertJsonPath('checks.cache', 'ok');
    }

    #[Test]
    public function health_endpoint_includes_version(): void
    {
        $response = $this->getJson('/health');

        $version = $response->json('version');
        $this->assertNotEmpty($version);
        $this->assertNotSame('unknown', $version);
    }

    #[Test]
    public function health_endpoint_includes_iso8601_timestamp(): void
    {
        $response = $this->getJson('/health');

        $timestamp = $response->json('timestamp');
        $this->assertNotEmpty($timestamp);
        // ISO 8601 format: 2026-03-28T12:00:00+00:00
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $timestamp);
    }

    #[Test]
    public function health_endpoint_is_publicly_accessible_without_auth(): void
    {
        // No login — should still return 200
        $response = $this->getJson('/health');

        $response->assertStatus(200);
    }
}
