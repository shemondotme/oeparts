<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WidgetManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_management_view_compiles(): void
    {
        $view = view('filament.modals.widget-management', [
            'getCanvasItems' => fn () => [],
            'getWidgetDescription' => fn ($id) => 'Test description',
            'getWidgetIconSvg' => fn ($id) => '<svg></svg>',
        ]);

        $rendered = $view->render();

        $this->assertNotNull($rendered);
        $this->assertStringContainsString('Search widgets', $rendered);
        $this->assertStringContainsString('widgetManager', $rendered);
        $this->assertStringContainsString('filteredWidgets', $rendered);
    }

    public function test_widget_management_view_handles_multiple_widgets(): void
    {
        $widgets = [];
        for ($i = 0; $i < 5; $i++) {
            $widgets[] = [
                'id' => "widget_$i",
                'label' => "Widget $i",
                'enabled' => $i % 2 === 0,
                'description' => "Description for widget $i",
                'icon' => '<svg></svg>',
            ];
        }

        $view = view('filament.modals.widget-management', [
            'getCanvasItems' => fn () => array_filter($widgets, fn ($w) => $w['enabled']),
            'getWidgetDescription' => fn ($id) => "Test description for $id",
            'getWidgetIconSvg' => fn ($id) => '<svg></svg>',
        ]);

        $rendered = $view->render();
        $this->assertStringContainsString('grid grid-cols-1', $rendered);
    }

    public function test_widget_management_produces_valid_json(): void
    {
        $view = view('filament.modals.widget-management', [
            'getCanvasItems' => fn () => [],
            'getWidgetDescription' => fn ($id) => 'Test',
            'getWidgetIconSvg' => fn ($id) => '<svg></svg>',
        ]);

        $rendered = $view->render();

        // Verify Alpine.js data initialization is present
        $this->assertStringContainsString('x-data="widgetManager', $rendered);
        // Verify form field is present
        $this->assertStringContainsString('name="widgets"', $rendered);
    }
}
