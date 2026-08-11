<?php

namespace Tests\Feature;

use App\Models\Condition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * <x-ui.condition-badge>'s string-slug prop path ran an uncached
 * Condition::where('slug', ...)->first() query per call — dormant today
 * (the only live call site passes an already-loaded Condition model
 * instead), but a per-call uncached lookup in a shared UI component is an
 * easy N+1 to wake up the moment someone uses the string-prop shape inside
 * a product loop. Now backed by CacheService::rememberConditionsBySlug().
 */
class ConditionBadgeCacheTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_string_slug_prop_resolves_the_real_condition_and_is_cached(): void
    {
        Condition::create([
            'slug' => 'used-a', 'name' => 'Used Grade A',
            'bg_color' => '#123456', 'text_color' => '#abcdef', 'is_active' => true,
        ]);

        $html = Blade::render('<x-ui.condition-badge :condition="$condition" />', ['condition' => 'used-a']);

        $this->assertStringContainsString('#123456', $html);
        $this->assertNotNull(Cache::get('conditions.by_slug'));
    }

    #[Test]
    public function an_unknown_slug_falls_back_to_a_titled_label(): void
    {
        $html = Blade::render('<x-ui.condition-badge :condition="$condition" />', ['condition' => 'nonexistent']);

        $this->assertStringContainsString('Nonexistent', $html);
    }

    #[Test]
    public function creating_a_condition_invalidates_the_by_slug_cache(): void
    {
        Blade::render('<x-ui.condition-badge :condition="$condition" />', ['condition' => 'brand-new']);
        $this->assertNotNull(Cache::get('conditions.by_slug'));

        Condition::create([
            'slug' => 'brand-new', 'name' => 'Brand New',
            'bg_color' => '#000000', 'text_color' => '#ffffff', 'is_active' => true,
        ]);

        $this->assertNull(Cache::get('conditions.by_slug'), 'ConditionObserver must forget the stale by-slug map');

        $html = Blade::render('<x-ui.condition-badge :condition="$condition" />', ['condition' => 'brand-new']);
        $this->assertStringContainsString('#000000', $html);
    }
}
