<?php

namespace Tests\Feature;

use App\Enums\SectionStatus;
use App\Models\Section;
use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The workshop banner may only mention an ISO certification when one is entered in
 * Settings > General > ISO Number, and then it shows exactly that value.
 */
class BannerIsoClaimTest extends TestCase
{
    use RefreshDatabase;

    private function bannerHtml(): string
    {
        $section = Section::create([
            'type' => 'banner', 'location' => 'homepage', 'title' => 'Banner',
            'content' => [
                'headline' => ['en' => 'Trade Pricing'],
                'features' => [[
                    'icon' => 'shield-check', 'title' => ['en' => 'Verified Genuine'],
                    'desc' => ['en' => 'Documented sourcing.'],
                    'desc_iso' => ['en' => 'Documented sourcing. Quality management: :iso.'],
                ]],
            ],
            'is_active' => true, 'status' => SectionStatus::Published, 'sort_order' => 80,
        ]);

        return view('components.sections.banner', ['section' => $section])->render();
    }

    private function setIso(string $value): void
    {
        Setting::updateOrCreate(['group' => 'general', 'key' => 'iso_number'], ['value' => $value, 'type' => 'string', 'is_encrypted' => false]);
        app(SettingsService::class)->forget('general');
    }

    #[Test]
    public function no_iso_claim_is_made_while_the_setting_is_empty(): void
    {
        $this->setIso('');

        $html = $this->bannerHtml();

        $this->assertStringContainsString('Documented sourcing.', $html);
        $this->assertStringNotContainsString('ISO', $html);
        $this->assertStringNotContainsString('Quality management', $html);
    }

    #[Test]
    public function the_entered_iso_number_is_shown_verbatim(): void
    {
        $this->setIso('ISO 9001:2015');

        $this->assertStringContainsString('Quality management: ISO 9001:2015.', $this->bannerHtml());
    }
}
