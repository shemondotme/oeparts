<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FactoryIntegrityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function page_factory_creates_valid_record_without_manual_overrides(): void
    {
        $page = Page::factory()->create();

        $this->assertDatabaseHas('pages', ['id' => $page->id]);
        $this->assertNotNull($page->created_by);
        $this->assertDatabaseHas('admins', ['id' => $page->created_by]);
    }

    #[Test]
    public function shipping_method_factory_creates_valid_record_without_manual_overrides(): void
    {
        $method = ShippingMethod::factory()->create();

        $this->assertDatabaseHas('shipping_methods', ['id' => $method->id]);
        $this->assertNotNull($method->zone_id);
        $this->assertDatabaseHas('shipping_zones', ['id' => $method->zone_id]);
    }

    #[Test]
    public function page_factory_bare_create_does_not_require_workaround(): void
    {
        $page = Page::factory()->create();

        $this->assertNotEmpty($page->slug);
        $this->assertEquals('published', $page->status->value);
    }
}
