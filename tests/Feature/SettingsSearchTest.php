<?php

namespace Tests\Feature;

use App\Filament\Support\SettingsRegistry;
use App\Filament\Support\SettingsSearchIndex;
use App\Models\Admin;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ctrl+K settings command palette (Phase 6). SettingsSearchIndex is a
 * hand-maintained static array — see its class docblock for why Filament's
 * built-in global search can't cover this. These tests can only guarantee
 * PAGE-level coverage (every registered settings page/tool appears at
 * least once) — there's no way to automatically verify every FIELD is
 * indexed, since the index has no structural link back to each page's
 * actual Filament form schema.
 */
class SettingsSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    private function superAdmin(): Admin
    {
        // is_active must be explicit — Admin::canAccessPanel() (Filament's
        // own panel-level gate, checked on every real HTTP request but NOT
        // by Livewire::test(), which mounts the component directly and
        // skips it) returns false for AdminFactory's default, silently
        // 403ing every real HTTP GET in this file otherwise.
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    #[Test]
    public function every_registered_settings_page_or_tool_has_at_least_one_search_index_row(): void
    {
        $registryBaseUrls = collect(SettingsRegistry::PAGES)->pluck('url')->all();

        $indexedBaseUrls = collect(SettingsSearchIndex::entries())
            ->map(fn (array $entry) => explode('?', $entry['url'])[0])
            ->unique()
            ->all();

        $missing = array_diff($registryBaseUrls, $indexedBaseUrls);

        $this->assertEmpty(
            $missing,
            'These SettingsRegistry pages/tools have zero SettingsSearchIndex rows, making them undiscoverable via Ctrl+K: ' . implode(', ', $missing)
        );
    }

    #[Test]
    public function searching_smtp_finds_the_email_setup_tab_on_store_operations(): void
    {
        $results = SettingsSearchIndex::search('SMTP');

        $this->assertNotEmpty($results);
        $match = collect($results)->first(fn (array $r) => $r['label'] === 'SMTP Server Host');

        $this->assertNotNull($match, 'Expected an "SMTP Server Host" result.');
        $this->assertSame('Store Operations', $match['page']);
        $this->assertSame('Email Setup', $match['tab']);
        $this->assertSame('/admin/settings/store-operations-settings?tab=8', $match['url']);
    }

    #[Test]
    public function the_deep_link_url_actually_activates_the_matched_tab(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        $match = collect(SettingsSearchIndex::search('SMTP'))->first(fn (array $r) => $r['label'] === 'SMTP Server Host');
        $url = $match['url'];

        // $url already carries the real ?tab=8 query string (e.g.
        // /admin/settings/store-operations-settings?tab=8) — a plain HTTP
        // GET is enough to prove StoreOperationsSettings' own
        // ->activeTab(fn () => (int) request()->query('tab', 1)) picks it
        // up and renders the matched tab's content.
        $response = $this->get($url);

        $response->assertStatus(200);
        // Every tab's own label renders in the DOM regardless of which tab
        // is active (Filament renders all tab headers + all tab panels,
        // toggling visibility client-side) — assertSee('Email Setup') alone
        // would pass even if activeTab silently stayed on tab 1. Assert the
        // actual Alpine x-data initializer instead, which is where
        // Tabs::getActiveTab()'s value (my ->activeTab() closure reading
        // request()->query('tab')) gets embedded server-side.
        $response->assertSee('activeTab: 8', false);
    }

    #[Test]
    public function endpoint_returns_matches_for_a_known_query(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        $response = $this->getJson('/admin/settings-search?q=SMTP');

        $response->assertOk();
        $response->assertJsonFragment(['label' => 'SMTP Server Host']);
    }

    #[Test]
    public function endpoint_returns_empty_results_for_a_query_under_two_characters(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        $response = $this->getJson('/admin/settings-search?q=a');

        $response->assertOk();
        $response->assertExactJson(['results' => []]);
    }

    #[Test]
    public function endpoint_rejects_a_role_without_settings_access(): void
    {
        $admin = Admin::factory()->create();
        $admin->assignRole('support');
        $this->actingAs($admin, 'admin');

        $response = $this->getJson('/admin/settings-search?q=SMTP');

        $response->assertStatus(403);
    }

    #[Test]
    public function endpoint_requires_authentication(): void
    {
        // AuthenticateAdmin (the 'auth.admin' middleware) always redirects
        // to the login route rather than returning 401 JSON, regardless of
        // the request's Accept header — matches its behavior on every other
        // admin.* route, not something specific to this endpoint.
        $response = $this->get('/admin/settings-search?q=SMTP');

        $response->assertRedirect(route('filament.admin.auth.login'));
    }
}
