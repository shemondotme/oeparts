<?php

namespace Tests\Feature;

use App\Enums\RedirectType;
use App\Filament\Resources\RedirectResource\Pages\ListRedirects;
use App\Jobs\ImportRedirectsFromCsv;
use App\Models\Admin;
use App\Models\Redirect;
use App\Services\RedirectLoopDetector;
use Database\Seeders\AdminSeeder;
use Database\Seeders\LanguagesSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Redirect groups (a label to keep a batch together) and expiry (a rule that stops working on its own). */
class RedirectGroupsAndExpiryTest extends TestCase
{
    use RefreshDatabase;

    private function rule(array $attrs): Redirect
    {
        return Redirect::create($attrs + ['to_url' => '/en/target', 'type' => RedirectType::Permanent, 'is_active' => true]);
    }

    public function test_a_rule_works_until_it_expires(): void
    {
        $this->seed([SettingsSeeder::class, LanguagesSeeder::class]);
        $this->rule(['from_url' => 'campaign', 'expires_at' => now()->addHour()]);

        $this->get('/campaign')->assertRedirect('/en/target');

        $this->travel(2)->hours();
        \Illuminate\Support\Facades\Cache::flush();

        $this->assertNotSame(301, $this->get('/campaign')->getStatusCode(), 'an expired rule is ignored');
    }

    public function test_a_rule_that_expires_inside_the_cache_window_stops_at_once(): void
    {
        $this->seed([SettingsSeeder::class, LanguagesSeeder::class]);
        $this->rule(['from_url' => 'flash', 'expires_at' => now()->addMinutes(5)]);

        $this->get('/flash')->assertRedirect('/en/target'); // now cached for up to 60s

        $this->travel(6)->minutes();                          // expired, cache still warm
        $this->assertNotSame(301, $this->get('/flash')->getStatusCode());
    }

    public function test_an_expired_pattern_rule_is_ignored(): void
    {
        $this->seed([SettingsSeeder::class, LanguagesSeeder::class]);
        $this->rule(['from_url' => 'old/*', 'match_type' => Redirect::MATCH_WILDCARD, 'to_url' => '/en/new/$1', 'expires_at' => now()->subMinute()]);

        $this->assertNotSame(301, $this->get('/old/thing')->getStatusCode());
    }

    public function test_the_active_scope_leaves_out_expired_rules(): void
    {
        $this->rule(['from_url' => 'a', 'expires_at' => now()->subDay()]);
        $this->rule(['from_url' => 'b', 'expires_at' => now()->addDay()]);
        $this->rule(['from_url' => 'c']);

        $this->assertEqualsCanonicalizing(['b', 'c'], Redirect::active()->pluck('from_url')->all());
    }

    public function test_the_csv_import_reads_group_and_expiry(): void
    {
        Storage::fake('local');
        $path = 'imports/redirects/g.csv';
        Storage::disk('local')->put($path, "from_url,to_url,type,is_active,group,expires_at\nold-a,/new-a,301,1,Migration 2026,2030-01-31 18:00\nold-b,/new-b,301,1,,\nold-c,/new-c,301,1,Migration 2026,not-a-date\n");

        (new ImportRedirectsFromCsv($path, 'Test', false))->handle(new RedirectLoopDetector);

        $a = Redirect::where('from_url', 'old-a')->first();
        $this->assertSame('Migration 2026', $a->group_name);
        $this->assertSame('2030-01-31 18:00', $a->expires_at->format('Y-m-d H:i'));
        $b = Redirect::where('from_url', 'old-b')->first();
        $this->assertNull($b->group_name);
        $this->assertNull($b->expires_at);
        $this->assertDatabaseMissing('redirects', ['from_url' => 'old-c']); // a bad date skips the row instead of guessing
    }

    public function test_the_list_filters_by_group_and_the_bulk_actions_set_group_and_expiry(): void
    {
        $this->seed([SettingsSeeder::class, LanguagesSeeder::class, RolesSeeder::class, AdminSeeder::class]);
        $this->actingAs(Admin::where('email', 'superadmin@oeparts.test')->firstOrFail(), 'admin');

        $one = $this->rule(['from_url' => 'one', 'group_name' => 'Alpha']);
        $two = $this->rule(['from_url' => 'two', 'group_name' => 'Beta']);
        $three = $this->rule(['from_url' => 'three']);

        Livewire::test(ListRedirects::class)->loadTable()
            ->assertOk()
            ->filterTable('group_name', 'Alpha')
            ->assertCanSeeTableRecords([$one])
            ->assertCanNotSeeTableRecords([$two, $three])
            ->removeTableFilters()
            ->callTableBulkAction('setGroup', [$two, $three], ['group_name' => 'Gamma']);

        $this->assertSame('Gamma', $two->fresh()->group_name);
        $this->assertSame('Gamma', $three->fresh()->group_name);

        Livewire::test(ListRedirects::class)->loadTable()
            ->callTableBulkAction('setExpiry', [$one], ['expires_at' => now()->subDay()->toDateTimeString()])
            ->filterTable('expired', true)
            ->assertCanSeeTableRecords([$one])
            ->assertCanNotSeeTableRecords([$two, $three]);
    }
}
