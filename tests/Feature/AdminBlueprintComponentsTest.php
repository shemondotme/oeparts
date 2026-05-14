<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminBlueprintComponentsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function blueprint_admin_components_render_shared_primitives(): void
    {
        $html = (string) $this->blade(<<<'BLADE'
            <x-admin.stat-card title="Orders" value="42" change="+12%" icon="heroicon-o-shopping-bag" />
            <x-admin.status-badge status="published">Published</x-admin.status-badge>
            <x-admin.empty-state title="No records" description="Create the first record." icon="heroicon-o-inbox" />
        BLADE);

        $this->assertStringContainsString('bp-card', $html);
        $this->assertStringContainsString('Orders', $html);
        $this->assertStringContainsString('42', $html);
        $this->assertStringContainsString('Published', $html);
        $this->assertStringContainsString('No records', $html);
    }
}
