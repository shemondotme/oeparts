<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Manufacturer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The two artisan commands the Playwright admin suite shells out to
 * (oeparts:e2e:cleanup-crud-leftovers, oeparts:e2e:resolve-edit-targets)
 * mutate/delete real rows in the shared dev DB, so they need the same
 * scrutiny as production code. Phase 22 (2026-09-24): an interrupted
 * crud-edit run left the real default "en" Language renamed "E2E Edited ...",
 * the next cleanup sweep deleted it as "leaked test data", and every /en/...
 * URL on the storefront 404'd — ~430 e2e tests failed for a reason unrelated
 * to the code under test. Neither command had any test coverage.
 */
class E2eFixtureSafetyCommandsTest extends TestCase
{
    use RefreshDatabase;

    private function makeLanguage(string $code, string $name, bool $default = false, int $sort = 1): Language
    {
        return Language::create([
            'code' => $code, 'name' => $name, 'native_name' => $name, 'locale' => $code.'_XX',
            'flag_emoji' => '🏳️', 'is_active' => true, 'is_default' => $default, 'sort_order' => $sort,
        ]);
    }

    #[Test]
    public function the_sweep_still_deletes_a_genuinely_leaked_created_row(): void
    {
        $this->makeLanguage('en', 'English', default: true);
        $leaked = $this->makeLanguage('zz', 'E2E Language 12345', sort: 9);

        $this->artisan('oeparts:e2e:cleanup-crud-leftovers')->assertSuccessful();

        $this->assertNull(Language::find($leaked->id), 'a created "E2E ..." leftover must still be swept');
        $this->assertNotNull(Language::where('code', 'en')->first());
    }

    #[Test]
    public function the_sweep_never_deletes_a_real_row_an_interrupted_edit_test_left_renamed(): void
    {
        $en = $this->makeLanguage('en', 'English', default: true);
        $de = $this->makeLanguage('de', 'German', sort: 2);

        // Exactly what crud-edit.spec.js leaves behind if it dies between
        // its rename-save and its revert-save.
        $de->update(['name' => 'E2E Edited abc123']);

        $this->artisan('oeparts:e2e:cleanup-crud-leftovers')
            ->expectsOutputToContain('interrupted crud-edit run left a REAL row renamed')
            ->assertSuccessful();

        $this->assertNotNull(Language::find($de->id), 'a renamed-but-real row must survive the sweep');
        $this->assertSame('E2E Edited abc123', $de->refresh()->name, 'and must be left as-is for manual restore, not silently altered');
        $this->assertNotNull(Language::find($en->id));
    }

    #[Test]
    public function the_default_language_is_never_swept_even_if_its_name_matches(): void
    {
        $en = $this->makeLanguage('en', 'E2E Language 99999', default: true);

        $this->artisan('oeparts:e2e:cleanup-crud-leftovers')->assertSuccessful();

        $this->assertNotNull(Language::find($en->id), 'the default language anchors every /{locale}/ route — it must never be deleted by a sweep');
    }

    #[Test]
    public function the_json_path_sweep_also_spares_a_renamed_real_row_but_deletes_a_leaked_one(): void
    {
        $real = Manufacturer::factory()->create(['name' => ['en' => 'E2E Edited real1', 'de' => 'Echt']]);
        $leaked = Manufacturer::factory()->create(['name' => ['en' => 'E2E Manufacturer 777', 'de' => 'x']]);
        $normal = Manufacturer::factory()->create(['name' => ['en' => 'Bosch', 'de' => 'Bosch']]);

        $this->artisan('oeparts:e2e:cleanup-crud-leftovers')->assertSuccessful();

        $this->assertNotNull(Manufacturer::find($real->id));
        $this->assertNull(Manufacturer::find($leaked->id));
        $this->assertNotNull(Manufacturer::find($normal->id));
    }

    #[Test]
    public function roles_follow_the_same_rules_and_super_admin_stays_protected(): void
    {
        $superAdmin = Role::create(['name' => 'super_admin', 'guard_name' => 'admin']);
        $renamedReal = Role::create(['name' => 'E2E Edited role77', 'guard_name' => 'admin']);
        $leakedLower = Role::create(['name' => 'e2e_role_abc', 'guard_name' => 'admin']);
        $leakedUpper = Role::create(['name' => 'E2E Role 55', 'guard_name' => 'admin']);
        $normal = Role::create(['name' => 'manager', 'guard_name' => 'admin']);

        $this->artisan('oeparts:e2e:cleanup-crud-leftovers')->assertSuccessful();

        $this->assertNotNull(Role::find($superAdmin->id));
        $this->assertNotNull(Role::find($renamedReal->id), 'a real role renamed by an interrupted edit must survive');
        $this->assertNotNull(Role::find($normal->id));
        $this->assertNull(Role::find($leakedLower->id));
        $this->assertNull(Role::find($leakedUpper->id));
    }

    #[Test]
    public function the_edit_target_resolver_never_hands_out_the_default_language(): void
    {
        $en = $this->makeLanguage('en', 'English', default: true);
        $de = $this->makeLanguage('de', 'German', sort: 2);
        $this->assertLessThan($de->id, $en->id, 'test premise: the default language really is the lowest id');

        $ids = json_decode($this->resolverOutput(), true);

        $this->assertSame($de->id, $ids['Language'], 'the edit suite renames its target — it must never be the default language');
    }

    #[Test]
    public function the_edit_target_resolver_still_skips_super_admin(): void
    {
        Role::create(['name' => 'super_admin', 'guard_name' => 'admin']);
        $other = Role::create(['name' => 'manager', 'guard_name' => 'admin']);

        $ids = json_decode($this->resolverOutput(), true);

        $this->assertSame($other->id, $ids['Role']);
    }

    private function resolverOutput(): string
    {
        Artisan::call('oeparts:e2e:resolve-edit-targets');

        return Artisan::output();
    }
}
