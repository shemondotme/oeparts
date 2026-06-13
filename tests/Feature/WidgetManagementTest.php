<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WidgetManagementTest extends TestCase
{
    use RefreshDatabase;

    private function sampleWidgets(): array
    {
        return [
            ['id' => 'kpi_stats',     'label' => 'KPI Stats',    'enabled' => true,  'description' => 'Sales and revenue KPIs.', 'icon' => '<svg></svg>'],
            ['id' => 'recent_orders', 'label' => 'Recent Orders','enabled' => false, 'description' => 'Latest customer orders.',  'icon' => '<svg></svg>'],
            ['id' => 'alerts',        'label' => 'Alerts',       'enabled' => true,  'description' => 'System alerts.',           'icon' => '<svg></svg>'],
        ];
    }

    public function test_widget_management_view_compiles(): void
    {
        $rendered = view('filament.modals.widget-management', [
            'widgets' => $this->sampleWidgets(),
        ])->render();

        $this->assertStringContainsString('Search widgets', $rendered);
        $this->assertStringContainsString('widgetManager', $rendered);
    }

    public function test_widget_management_view_renders_grid(): void
    {
        $rendered = view('filament.modals.widget-management', [
            'widgets' => $this->sampleWidgets(),
        ])->render();

        $this->assertStringContainsString('grid-cols-2', $rendered);
        $this->assertStringContainsString('filteredWidgets', $rendered);
        $this->assertStringContainsString('x-show', $rendered);
        $this->assertStringContainsString('x-html', $rendered);
    }

    public function test_widget_management_view_has_wire_sync(): void
    {
        $rendered = view('filament.modals.widget-management', [
            'widgets' => $this->sampleWidgets(),
        ])->render();

        // Livewire wire sync is in the Alpine component
        $this->assertStringContainsString('widgetSelections', $rendered);
        $this->assertStringContainsString('toggleWidget', $rendered);
    }

    public function test_widget_management_view_handles_empty_widgets(): void
    {
        $rendered = view('filament.modals.widget-management', [
            'widgets' => [],
        ])->render();

        $this->assertStringContainsString('widgetManager', $rendered);
        $this->assertStringContainsString('No widgets match', $rendered);
    }
}
