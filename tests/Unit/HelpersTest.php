<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HelpersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function lazy_loading_attr_defaults_to_lazy(): void
    {
        $this->assertSame('lazy', lazy_loading_attr());
    }

    #[Test]
    public function lazy_loading_attr_respects_the_real_admin_saved_off_value(): void
    {
        // SettingsPage::persistChanges() saves a Toggle's OFF state as the
        // literal string 'false' (not '0') — same gotcha this codebase's
        // other performance toggles already guard against.
        Setting::updateOrCreate(
            ['group' => 'performance', 'key' => 'lazy_load_images'],
            ['value' => 'false', 'type' => 'boolean', 'is_encrypted' => false]
        );
        app(SettingsService::class)->forget('performance');

        $this->assertSame('eager', lazy_loading_attr());
    }
}
