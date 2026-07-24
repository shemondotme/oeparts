<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomeRedirectAndHealthCheckTest extends TestCase
{
    /**
     * Root URL redirects to /{lang}/
     */
    public function test_root_redirects_to_language_prefix(): void
    {
        $this->withoutExceptionHandling();

        $response = $this->get('/');

        $response->assertRedirect();
    }

    /**
     * Health endpoint returns 200.
     */
    public function test_health_endpoint_returns_ok(): void
    {
        $this->withoutExceptionHandling();

        $response = $this->get('/up');

        $response->assertStatus(200);
    }
}
