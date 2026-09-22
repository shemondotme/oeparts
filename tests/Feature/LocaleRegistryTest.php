<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Support\LocaleRegistry;
use Database\Seeders\LanguagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SetLocale, the {lang} route constraint, SeoService, and SitemapService all
 * used to independently hardcode the same five-locale list — activating or
 * deactivating a language via LanguageResource had zero effect anywhere on
 * the storefront. LocaleRegistry is the single source of truth now, backed
 * by the `languages` table.
 */
class LocaleRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LanguagesSeeder::class);
    }

    #[Test]
    public function deactivating_a_language_removes_it_from_the_registry(): void
    {
        $this->assertContains('es', LocaleRegistry::codes());

        Language::where('code', 'es')->first()->update(['is_active' => false]);

        $this->assertNotContains('es', LocaleRegistry::codes());
    }

    #[Test]
    public function deactivating_a_language_removes_it_from_the_route_pattern(): void
    {
        // The {lang} route constraint is compiled once per app boot (a
        // Laravel/PHP-FPM constraint, not specific to this fix) — this
        // checks the pattern-building logic directly rather than through an
        // actual route match, since a single test process can't observe a
        // fresh boot mid-test. On a real deployment without route:cache,
        // routes/web.php re-evaluates this on every request, so the next
        // request after deactivating picks it up; with route:cache active,
        // a route:clear is needed, same as after any other route change.
        $this->assertStringContainsString('es', LocaleRegistry::routePattern());

        Language::where('code', 'es')->first()->update(['is_active' => false]);

        $this->assertStringNotContainsString('es', LocaleRegistry::routePattern());
    }

    #[Test]
    public function a_newly_activated_language_becomes_routable(): void
    {
        Language::create([
            'code' => 'it', 'name' => 'Italian', 'native_name' => 'Italiano',
            'locale' => 'it_IT', 'flag_emoji' => '🇮🇹', 'is_active' => true, 'sort_order' => 6,
        ]);

        $this->assertContains('it', LocaleRegistry::codes());
    }

    #[Test]
    public function deactivated_language_is_excluded_from_the_switcher(): void
    {
        Language::where('code', 'fr')->first()->update(['is_active' => false]);

        $response = $this->get('/en/');

        $response->assertOk();
        $response->assertDontSee('Français');
    }

    #[Test]
    public function registry_falls_back_to_the_default_five_when_the_table_is_unusable(): void
    {
        Schema::drop('languages');
        LocaleRegistry::forget();

        $this->assertSame(['en', 'de', 'lt', 'fr', 'es'], LocaleRegistry::codes());
    }

    // Phase 14 (Monitoring/Logging Audit): languages()'s catch used to be
    // inside the rememberForever() callback and completely silent — the
    // fix (move the try/catch to wrap the whole rememberForever() call,
    // log a warning) is the exact same pattern already verified end-to-end
    // in MenuRegistryTest::a_broken_menus_query_logs_a_warning_and_is_not_
    // cached_forever(). No equivalent synthetic-exception test here: a
    // dropped column doesn't reliably throw against SQLite (its grammar
    // double-quotes identifiers, and SQLite silently reinterprets an
    // unresolvable quoted identifier as a string literal rather than
    // erroring) and, unlike MenuRegistry::items(), this method has no
    // eager-loaded related table to drop instead. A DB::purge('sqlite')-
    // based connection break was tried and reverted: on the :memory:
    // driver this doesn't just reconnect, it destroys and replaces the
    // whole in-memory database, corrupting RefreshDatabase's state for
    // every other test in the same PHPUnit process (confirmed live — it
    // broke 23 unrelated tests with "table migrations already exists").
}
