<?php

namespace Tests\Feature;

use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OemUrlNormalizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function lowercase_oem_segment_matches_the_route_and_redirects_to_uppercase(): void
    {
        // Before the route-regex fix, a lowercase segment failed to match
        // `frontend.search.results` at all (hard 404, before any middleware
        // ran) — NormalizeOemUrl never got a chance to 301 it. This is the
        // exact request shape Google's own SearchAction/sitelinks box can
        // produce, since it substitutes a raw, un-normalized user query.
        $response = $this->get('/en/parts/abc123');

        $response->assertRedirect('/en/parts/ABC123');
        $response->assertStatus(301);
    }

    #[Test]
    public function mixed_case_with_punctuation_normalizes_to_uppercase_alphanumeric(): void
    {
        $response = $this->get('/en/parts/06l-906-036-l');

        $response->assertRedirect('/en/parts/06L906036L');
        $response->assertStatus(301);
    }

    #[Test]
    public function lowercase_oem_on_the_detail_route_preserves_the_idslug_instead_of_downgrading_to_the_hub_route(): void
    {
        // NormalizeOemUrl used to hardcode frontend.search.results as the
        // redirect target regardless of which route actually matched —
        // silently dropping idSlug and sending a wrong-case hit on the
        // per-product detail page to the generic hub instead.
        $response = $this->get('/en/parts/abc123/42-some-part');

        $response->assertRedirect('/en/parts/ABC123/42-some-part');
        $response->assertStatus(301);
    }

    #[Test]
    public function lowercase_oem_on_the_results_route_preserves_the_query_string(): void
    {
        // route()/URL::route() only encodes named route parameters — a
        // filtered link's manufacturer/condition/sort/etc. query string was
        // silently dropped on this 301 otherwise. Symfony's
        // Request::getQueryString() re-sorts params alphabetically by key
        // (its own normalization, unrelated to this fix), so this asserts
        // on the parsed query rather than an exact string.
        $response = $this->get('/en/parts/abc123?manufacturer=5&condition=new');

        $response->assertStatus(301);
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('http://localhost/en/parts/ABC123?', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        ksort($query);
        $this->assertSame(['condition' => 'new', 'manufacturer' => '5'], $query);
    }

    #[Test]
    public function already_uppercase_oem_does_not_redirect(): void
    {
        $condition = Condition::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'bg_color' => '#ecfdf5', 'text_color' => '#065f46', 'is_active' => true]
        );
        $manufacturer = Manufacturer::create([
            'name' => ['en' => 'Test Manufacturer', 'de' => 'Test Manufacturer', 'lt' => 'Test Manufacturer', 'fr' => 'Test Manufacturer', 'es' => 'Test Manufacturer'],
            'slug' => 'test-manufacturer',
            'country_code' => 'DE',
            'is_active' => true,
        ]);
        Product::create([
            'manufacturer_id' => $manufacturer->id,
            'oem_number' => '06L906036L',
            'normalized_oem' => '06L906036L',
            'condition_id' => $condition->id,
            'price' => '100.00',
            'is_in_stock' => true,
            'is_active' => true,
        ]);

        // Already normalized — the point here is only that it's NOT a
        // redirect, decoupled from the zero-results page's status code
        // (which a later step changes from 200 to 404).
        $response = $this->get('/en/parts/06L906036L');

        $response->assertStatus(200);
        $response->assertSeeText('06L906036L');
    }
}
