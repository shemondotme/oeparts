<?php

namespace Tests\Feature;

use App\Models\Manufacturer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ManufacturerController::index() used to hardcode name->en for the
 * letter-filter and A-Z sort regardless of $lang, while the view groups and
 * displays manufacturers by the localized name — non-English visitors saw
 * letter groups built from the wrong language, and the letter-filter query
 * itself matched against the English name.
 */
class ManufacturerLocaleIndexTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function letter_filter_matches_against_the_current_locales_name(): void
    {
        // English name starts with "A", German name starts with "Z" — a
        // German-locale request for letter=Z must find it via the German
        // name, not fail to match against the English one.
        Manufacturer::create([
            'name' => ['en' => 'Auto Parts Co', 'de' => 'Zubehör GmbH'],
            'slug' => 'auto-parts-co', 'country_code' => 'DE', 'is_active' => true,
        ]);

        $response = $this->get('/de/brands?letter=Z');

        $response->assertOk();
        $response->assertSee('Zubehör GmbH');
    }

    #[Test]
    public function letter_filter_falls_back_to_english_name_when_locale_translation_missing(): void
    {
        Manufacturer::create([
            'name' => ['en' => 'Bosch Parts'],
            'slug' => 'bosch-parts', 'country_code' => 'DE', 'is_active' => true,
        ]);

        $response = $this->get('/de/brands?letter=B');

        $response->assertOk();
        $response->assertSee('Bosch Parts');
    }

    #[Test]
    public function sort_order_follows_the_current_locales_name(): void
    {
        Manufacturer::create(['name' => ['en' => 'Alpha', 'de' => 'Zebra'], 'slug' => 'alpha', 'country_code' => 'DE', 'is_active' => true]);
        Manufacturer::create(['name' => ['en' => 'Beta', 'de' => 'Anfang'], 'slug' => 'beta', 'country_code' => 'DE', 'is_active' => true]);

        $response = $this->get('/de/brands');
        $response->assertOk();

        // German names sorted: "Anfang" (Beta) before "Zebra" (Alpha).
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'Zebra'), strpos($content, 'Anfang'));
    }
}
