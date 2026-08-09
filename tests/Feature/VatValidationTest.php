<?php

namespace Tests\Feature;

use App\Services\ViesResult;
use App\Services\ViesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * VatValidationController used to prioritize a separately-submitted
 * country_code (e.g. from a shipping-country dropdown) over the VAT
 * number's own embedded alpha prefix — it only stripped the prefix when it
 * happened to match country_code. When a customer entered a VAT number from
 * a different country than the one selected, the mismatched pair
 * (e.g. country_code=DE, vat_number=FR123456789 — prefix still attached)
 * was sent straight to VIES, guaranteeing a false "invalid" result.
 */
class VatValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    #[Test]
    public function the_vat_numbers_own_prefix_wins_over_a_conflicting_country_code_param(): void
    {
        $this->mock(ViesService::class, function ($mock) {
            $mock->shouldReceive('validate')
                ->once()
                ->with('FR', '123456789')
                ->andReturn(new ViesResult(valid: true, reason: null, countryCode: 'FR', vatNumber: '123456789'));
        });

        $response = $this->postJson('/api/validate-vat', [
            'vat_number' => 'FR123456789',
            'country_code' => 'DE',
        ]);

        $response->assertOk()->assertJson(['success' => true, 'valid' => true, 'country_code' => 'FR']);
    }

    #[Test]
    public function a_bare_number_falls_back_to_the_submitted_country_code(): void
    {
        $this->mock(ViesService::class, function ($mock) {
            $mock->shouldReceive('validate')
                ->once()
                ->with('DE', '123456789')
                ->andReturn(new ViesResult(valid: true, reason: null, countryCode: 'DE', vatNumber: '123456789'));
        });

        $response = $this->postJson('/api/validate-vat', [
            'vat_number' => '123456789',
            'country_code' => 'DE',
        ]);

        $response->assertOk()->assertJson(['success' => true, 'valid' => true, 'country_code' => 'DE']);
    }

    #[Test]
    public function a_prefixed_number_with_no_separate_country_code_uses_its_own_prefix(): void
    {
        $this->mock(ViesService::class, function ($mock) {
            $mock->shouldReceive('validate')
                ->once()
                ->with('LT', '100001919314')
                ->andReturn(new ViesResult(valid: true, reason: null, countryCode: 'LT', vatNumber: '100001919314'));
        });

        $response = $this->postJson('/api/validate-vat', [
            'vat_number' => 'LT100001919314',
        ]);

        $response->assertOk()->assertJson(['success' => true, 'valid' => true]);
    }
}
