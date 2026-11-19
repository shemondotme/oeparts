<?php

namespace Tests\Feature;

use App\Services\SettingsService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The admin's single-page navigation can be switched off in Settings > Performance (plain page loads instead).
 */
class AdminSpaSwitchTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_admin_swaps_pages_in_place_by_default(): void
    {
        $this->assertTrue(Filament::getPanel('admin')->hasSpaMode());
    }

    #[Test]
    public function switching_it_off_makes_the_admin_load_full_pages(): void
    {
        app(SettingsService::class)->set('performance.admin_spa_enabled', 'false');

        $this->assertFalse(Filament::getPanel('admin')->hasSpaMode());

        app(SettingsService::class)->set('performance.admin_spa_enabled', 'true');

        $this->assertTrue(Filament::getPanel('admin')->hasSpaMode());
    }
}
