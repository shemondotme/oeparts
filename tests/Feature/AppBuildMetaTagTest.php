<?php

namespace Tests\Feature;

use App\Support\AppBuildId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppBuildMetaTagTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function storefront_page_includes_app_build_meta_tag(): void
    {
        $response = $this->get('/en/');

        $response->assertOk();
        $response->assertSee('name="app-build"', false);
        $response->assertSee('content="'.AppBuildId::current().'"', false);
    }
}
