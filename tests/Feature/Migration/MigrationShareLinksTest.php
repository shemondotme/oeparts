<?php

namespace Tests\Feature\Migration;

use App\Filament\Widgets\Migration\MigrationPackagesTable;
use App\Filament\Widgets\Migration\MigrationShareLinksTable;
use App\Models\Admin;
use App\Models\PackageShareLink;
use App\Security\PackageAudit;
use App\Services\Migration\Export\PackageOutbox;
use App\Services\Migration\Transport\ShareLinkService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * System > Migration > Share links: what became of every one-time download link, and the way to switch one off.
 *
 * The exports folder and the audit history are temporary. The tab shows a link by its package's FILE NAME only, never a path, and
 * nothing that could open the download; revoking asks for the admin's password again and shares the five-tries lock of every other
 * password-gated action of the Migration pages.
 */
class MigrationShareLinksTest extends TestCase
{
    use RefreshDatabase;

    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oeparts-share-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/exports', 0700, true);
        $this->app->instance(PackageOutbox::class, new PackageOutbox($this->dir.'/exports'));
        $this->app->instance(PackageAudit::class, new PackageAudit($this->dir.'/history.ndjson'));

        $body = 'OEPARTS1'.random_bytes(5000);
        file_put_contents($this->dir.'/exports/site.oeparts', $body);
        file_put_contents($this->dir.'/exports/site.oeparts.json', json_encode(['sha256' => hash('sha256', $body), 'size' => strlen($body), 'expires_at' => now()->addDays(7)->toIso8601String()]));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/{,*/}{,.}*', GLOB_BRACE) ?: [] as $file) {
            is_file($file) ? @unlink($file) : null;
        }

        @rmdir($this->dir.'/exports');
        @rmdir($this->dir);

        parent::tearDown();

        gc_collect_cycles();
    }

    private function admin(string $role = 'super_admin'): Admin
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole($role);
        RateLimiter::clear('reauth:'.$admin->getKey().'|127.0.0.1');

        return $admin;
    }

    /** @param  array<string, mixed>  $fields */
    private function link(array $fields = []): PackageShareLink
    {
        return PackageShareLink::create($fields + [
            'token_hash' => hash('sha256', random_bytes(16)),
            'package_name' => 'site.oeparts',
            'package_size' => 5008,
            'state' => PackageShareLink::ACTIVE,
            'expires_at' => now()->addDay(),
        ]);
    }

    // ---- who sees the tab -----------------------------------------------------------------------------------------

    #[Test]
    public function only_someone_who_may_export_can_see_the_tab_and_the_rest_see_an_empty_list(): void
    {
        $this->link();

        $exporter = $this->admin('support');
        $exporter->givePermissionTo('export migration');
        $importer = $this->admin('support');
        $importer->givePermissionTo('import migration');

        $this->actingAs($this->admin(), 'admin');
        $this->assertTrue(MigrationShareLinksTable::canView());

        $this->actingAs($exporter, 'admin');
        $this->assertTrue(MigrationShareLinksTable::canView());

        // A link hands out a full copy of the shop's data: importing alone is not enough, and nothing is listed for them.
        $this->actingAs($importer, 'admin');
        $this->assertFalse(MigrationShareLinksTable::canView());
        $this->assertTrue(MigrationPackagesTable::canView(), 'the page itself is open to an importer');
        Livewire::test(MigrationShareLinksTable::class)->assertDontSee('site.oeparts');

        $this->actingAs($this->admin('support'), 'admin');
        $this->assertFalse(MigrationShareLinksTable::canView());
        Livewire::test(MigrationShareLinksTable::class)->assertDontSee('site.oeparts');
    }

    // ---- the list -------------------------------------------------------------------------------------------------

    #[Test]
    public function every_link_is_listed_newest_first_with_its_state(): void
    {
        $active = $this->link(['package_name' => 'one-active.oeparts']);
        $used = $this->link(['package_name' => 'two-used.oeparts', 'state' => PackageShareLink::CONSUMED, 'first_claimed_at' => now()->subHour(), 'consumed_at' => now()->subMinutes(30), 'bytes_served' => 5008]);
        $late = $this->link(['package_name' => 'three-late.oeparts', 'expires_at' => now()->subMinute()]); // still marked active in the table: the clock decides what is shown
        $revoked = $this->link(['package_name' => 'four-revoked.oeparts', 'state' => PackageShareLink::REVOKED, 'revoked_at' => now()]);
        $expired = $this->link(['package_name' => 'five-expired.oeparts', 'state' => PackageShareLink::EXPIRED, 'expires_at' => now()->subDay()]);
        $refused = $this->link(['package_name' => 'six-refused.oeparts', 'deny_count' => 3]);
        $this->actingAs($this->admin(), 'admin');

        $table = Livewire::test(MigrationShareLinksTable::class)
            ->assertSuccessful()
            ->assertSeeInOrder(['six-refused.oeparts', 'five-expired.oeparts', 'four-revoked.oeparts', 'three-late.oeparts', 'two-used.oeparts', 'one-active.oeparts']);

        $table->assertTableColumnStateSet('state', 'Active', (string) $active->id)
            ->assertTableColumnStateSet('state', 'Used', (string) $used->id)
            ->assertTableColumnStateSet('state', 'Expired', (string) $late->id)
            ->assertTableColumnStateSet('state', 'Revoked', (string) $revoked->id)
            ->assertTableColumnStateSet('state', 'Expired', (string) $expired->id)
            ->assertTableColumnStateSet('uses', 1, (string) $used->id)
            ->assertTableColumnStateSet('uses', 0, (string) $active->id)
            ->assertTableColumnStateSet('refused', 3, (string) $refused->id);

    }

    #[Test]
    public function a_link_is_shown_by_the_file_name_of_its_package_never_a_path_and_never_what_could_open_it(): void
    {
        $this->link(['package_name' => 'sub/dir/other.oeparts']);
        $this->link(['package_name' => 'C:\\secret\\place\\third.oeparts']);
        $this->link(['package_name' => '../../.env']);
        $made = app(ShareLinkService::class)->create('site.oeparts', null, 2);
        $this->actingAs($this->admin(), 'admin');

        $shown = Livewire::test(MigrationShareLinksTable::class)
            ->assertSee('other.oeparts')
            ->assertSee('third.oeparts')
            ->assertSee('.env')
            ->assertDontSee('sub/dir')
            ->assertDontSee('secret\\place', false)
            ->assertDontSee('../')
            ->assertDontSee($this->dir)
            ->assertDontSee($made['token'])
            ->assertDontSee(hash('sha256', $made['token']))
            ->assertDontSee($made['share']);

        $html = $shown->html();
        $this->assertStringNotContainsString('token_hash', $html);
        $this->assertStringNotContainsString('claim_hash', $html);
        $this->assertStringNotContainsString('sha256=', $html, 'the share string, which carries the package hash, is not on this tab');
    }

    #[Test]
    public function with_no_links_the_tab_says_where_to_make_one(): void
    {
        $this->actingAs($this->admin(), 'admin');

        Livewire::test(MigrationShareLinksTable::class)
            ->assertSee('No share links yet')
            ->assertSee('Packages tab');
    }

    #[Test]
    public function the_tab_repeats_that_the_passphrase_travels_by_another_route(): void
    {
        $this->actingAs($this->admin(), 'admin');

        Livewire::test(MigrationShareLinksTable::class)->assertSee('send the passphrase by a different route than the link');
    }

    // ---- revoking -------------------------------------------------------------------------------------------------

    #[Test]
    public function revoke_is_offered_only_for_a_link_that_can_still_be_used(): void
    {
        $active = $this->link();
        $used = $this->link(['state' => PackageShareLink::CONSUMED]);
        $revoked = $this->link(['state' => PackageShareLink::REVOKED]);
        $late = $this->link(['expires_at' => now()->subMinute()]);
        $this->actingAs($this->admin(), 'admin');

        Livewire::test(MigrationShareLinksTable::class)
            ->assertTableActionVisible('revoke', (string) $active->id)
            ->assertTableActionHidden('revoke', (string) $used->id)
            ->assertTableActionHidden('revoke', (string) $revoked->id)
            ->assertTableActionHidden('revoke', (string) $late->id);
    }

    #[Test]
    public function revoking_needs_the_right_password_and_then_closes_the_link_for_good(): void
    {
        $made = app(ShareLinkService::class)->create('site.oeparts', null, 2);
        $id = (string) $made['link']->getKey();
        $this->actingAs($this->admin(), 'admin');

        Livewire::test(MigrationShareLinksTable::class)
            ->callTableAction('revoke', $id, ['password' => 'wrong'])
            ->assertHasTableActionErrors(['password']);
        $this->assertSame(PackageShareLink::ACTIVE, $made['link']->fresh()->state, 'a wrong password changes nothing');
        $this->assertNull($made['link']->fresh()->revoked_at);

        Livewire::test(MigrationShareLinksTable::class)
            ->callTableAction('revoke', $id, ['password' => 'password'])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Link revoked');

        $link = $made['link']->fresh();
        $this->assertSame(PackageShareLink::REVOKED, $link->state);
        $this->assertNotNull($link->revoked_at);
        $this->assertFalse(app(ShareLinkService::class)->claim($made['token'], null, '203.0.113.5')['ok'], 'the link no longer opens the download');
        $this->assertStringContainsString('share.revoked', (string) file_get_contents($this->dir.'/history.ndjson'));
        $this->assertFileExists($this->dir.'/exports/site.oeparts', 'the package itself is not touched');

        Livewire::test(MigrationShareLinksTable::class)->assertTableColumnStateSet('state', 'Revoked', $id);
    }

    #[Test]
    public function after_five_wrong_passwords_even_the_right_one_is_refused_for_a_while(): void
    {
        $link = $this->link();
        $this->actingAs($this->admin(), 'admin');

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(MigrationShareLinksTable::class)->callTableAction('revoke', (string) $link->id, ['password' => 'bad'.$i]);
        }

        Livewire::test(MigrationShareLinksTable::class)
            ->callTableAction('revoke', (string) $link->id, ['password' => 'password'])
            ->assertHasTableActionErrors(['password']);

        $this->assertSame(PackageShareLink::ACTIVE, $link->fresh()->state, 'the lock held: the link was not revoked');
    }

    #[Test]
    public function a_revoked_link_offers_no_second_revoke_and_leaves_one_history_line(): void
    {
        $link = $this->link();
        $this->actingAs($this->admin(), 'admin');

        Livewire::test(MigrationShareLinksTable::class)->callTableAction('revoke', (string) $link->id, ['password' => 'password'])->assertNotified('Link revoked');
        Livewire::test(MigrationShareLinksTable::class)->assertTableActionHidden('revoke', (string) $link->id);
        app(ShareLinkService::class)->revoke((int) $link->id); // a second revoke, from anywhere, changes nothing

        $this->assertSame(1, substr_count((string) file_get_contents($this->dir.'/history.ndjson'), 'share.revoked'), 'one history line');
    }
}
