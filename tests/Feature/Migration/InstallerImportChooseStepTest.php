<?php

namespace Tests\Feature\Migration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\OpensInstallerPackages;
use Tests\TestCase;

/**
 * Step 4 of the installer's import branch: what to bring, whether this is the real shop, and the address of the new site.
 *
 * Real packages, real unlock; every folder involved is temporary.
 */
class InstallerImportChooseStepTest extends TestCase
{
    use OpensInstallerPackages;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareOpenedPackageTest();
    }

    protected function tearDown(): void
    {
        $this->cleanUpOpenedPackageTest();

        parent::tearDown();
    }

    /** @param  array<int, string>  $groups */
    private function openPackageWith(array $groups, array $export = []): void
    {
        $this->buildPackage('shop.oeparts', ['export' => array_replace(['groups' => $groups, 'preset' => 'full_live'], $export)]);
        $this->ownerAtPackageStep();
        $this->openPackage()->assertRedirect(route('installer.import.package'));
    }

    /** @return array<string, mixed> */
    private function valid(array $override = []): array
    {
        return array_replace(['selection' => 'preset:catalog_only', 'launch' => 'live', 'site_url' => 'http://shop.example'], $override);
    }

    private const CATALOG_PACKAGE = ['core', 'catalog', 'catalog_files', 'media'];

    // --- getting here ---

    #[Test]
    public function the_step_needs_an_open_package(): void
    {
        $this->buildPackage();
        $this->ownerAtPackageStep();

        $this->get('/install/import/choose')->assertRedirect(route('installer.import.package'));
        $this->post('/install/import/choose', $this->valid())->assertRedirect(route('installer.import.package'));
        $this->assertSame([], $this->wizard()->choices());
    }

    #[Test]
    public function the_open_package_leads_to_this_step(): void
    {
        $this->openPackageWith(self::CATALOG_PACKAGE);

        $this->get('/install/import/package')
            ->assertOk()
            ->assertSee('Next: choose what to bring')
            ->assertSee(route('installer.import.choose'), false);
    }

    #[Test]
    public function only_the_owner_can_see_or_change_the_choices(): void
    {
        $this->openPackageWith(self::CATALOG_PACKAGE);
        $before = (string) file_get_contents($this->wizard()->path());

        $this->aStranger()->withSession(['installer.db_configured' => true]);
        $this->get('/install/import/choose')->assertForbidden();
        $this->post('/install/import/choose', $this->valid())->assertForbidden();

        $this->assertSame($before, (string) file_get_contents($this->wizard()->path()));
    }

    // --- what the page offers ---

    #[Test]
    public function only_the_presets_the_package_can_honour_are_offered(): void
    {
        $this->openPackageWith(self::CATALOG_PACKAGE);

        $page = $this->get('/install/import/choose');

        $page->assertOk();
        $page->assertSee('Catalog only');
        $page->assertDontSee('Full live copy');
        $page->assertDontSee('Staging copy, personal data scrambled');
        $page->assertSee('Let me choose');
    }

    #[Test]
    public function the_group_list_shows_only_what_the_package_holds_and_core_cannot_be_unticked(): void
    {
        $this->openPackageWith(self::CATALOG_PACKAGE);

        $page = $this->get('/install/import/choose');

        $page->assertSee('Site basics');
        $page->assertSee('Products');
        $page->assertSee('Product pictures');
        $page->assertSee('Media library');
        $page->assertDontSee('Customers');
        $page->assertDontSee('Orders');
        $this->assertMatchesRegularExpression('/id="group-core"[^>]*checked[^>]*disabled|id="group-core"[^>]*disabled[^>]*checked/', $page->getContent());
    }

    #[Test]
    public function the_package_s_own_preset_is_the_starting_choice_but_live_or_test_has_no_default(): void
    {
        $this->openPackageWith(self::CATALOG_PACKAGE, ['preset' => 'catalog_only']);

        $page = $this->get('/install/import/choose');

        $this->assertMatchesRegularExpression('/value="preset:catalog_only"[^>]*checked/', $page->getContent());
        $this->assertDoesNotMatchRegularExpression('/name="launch" value="live"[^>]*checked/', $page->getContent());
        $this->assertDoesNotMatchRegularExpression('/name="launch" value="staging"[^>]*checked/', $page->getContent());
        $page->assertSee('Is this your real, live site?');
        $page->assertSee('Yes, this will be my live site');
        $page->assertSee('No, this is a test copy');
    }

    #[Test]
    public function the_address_starts_as_the_one_in_use_and_the_page_is_honest_about_the_checks_to_come(): void
    {
        $this->openPackageWith(self::CATALOG_PACKAGE);

        $page = $this->get('/install/import/choose');

        $page->assertSee('value="http://localhost"', false);
        $page->assertSee('We check what your choice needs once the database is prepared, and ask you to confirm anything we add.');
        $page->assertSee('What we will adjust for you');
    }

    #[Test]
    public function the_secrets_box_says_what_the_package_does_and_does_not_carry(): void
    {
        $this->openPackageWith(self::CATALOG_PACKAGE);
        $this->get('/install/import/choose')->assertSee('are not in the package');

        $this->post('/install/import/package/forget');
        unlink($this->inboxDir().DIRECTORY_SEPARATOR.'shop.oeparts'); // the writer never overwrites a package
        $this->buildPackage('shop.oeparts', ['export' => ['groups' => self::CATALOG_PACKAGE, 'secrets_included' => true]]);
        $this->openPackage()->assertRedirect();
        $this->get('/install/import/choose')->assertSee('re-locked for this server');
    }

    #[Test]
    public function a_scrambled_package_says_so(): void
    {
        $this->openPackageWith(self::CATALOG_PACKAGE, ['anonymised' => true]);

        $this->get('/install/import/choose')->assertSee('were scrambled when it was exported');
    }

    // --- saving ---

    #[Test]
    public function a_preset_with_live_and_an_address_is_saved(): void
    {
        $this->openPackageWith(self::CATALOG_PACKAGE);

        $this->post('/install/import/choose', $this->valid())->assertRedirect(route('installer.import.choose'));

        $choices = $this->wizard()->choices();
        $this->assertSame('preset:catalog_only', $choices['selection']);
        $this->assertSame('catalog_only', $choices['preset']);
        $this->assertEqualsCanonicalizing(['core', 'catalog', 'catalog_files', 'media'], $choices['groups']);
        $this->assertSame('live', $choices['launch']);
        $this->assertSame('http://shop.example', $choices['url']);

        $this->get('/install/import/choose')->assertSee('Your choices are saved.');
    }

    #[Test]
    public function a_hand_picked_selection_always_carries_core_once(): void
    {
        $this->openPackageWith(self::CATALOG_PACKAGE);

        $this->post('/install/import/choose', $this->valid(['selection' => 'custom', 'groups' => ['catalog', 'catalog', 'media', 'core']]))
            ->assertRedirect(route('installer.import.choose'));

        $choices = $this->wizard()->choices();
        $this->assertSame('custom', $choices['selection']);
        $this->assertNull($choices['preset']);
        $this->assertSame(['core', 'catalog', 'media'], $choices['groups']);
    }

    #[Test]
    public function a_test_copy_is_saved_as_staging(): void
    {
        $this->openPackageWith(self::CATALOG_PACKAGE);

        $this->post('/install/import/choose', $this->valid(['launch' => 'staging']))->assertRedirect();

        $this->assertSame('staging', $this->wizard()->choices()['launch']);
    }

    #[Test]
    public function the_saved_choices_come_back_on_the_next_visit(): void
    {
        $this->openPackageWith(self::CATALOG_PACKAGE);
        $this->post('/install/import/choose', $this->valid(['launch' => 'staging', 'site_url' => 'http://test.shop.example:8080']))->assertRedirect();

        $page = $this->get('/install/import/choose');

        $this->assertMatchesRegularExpression('/name="launch" value="staging"[^>]*checked/', $page->getContent());
        $page->assertSee('value="http://test.shop.example:8080"', false);
    }

    // --- refusing ---

    #[Test]
    public function live_or_test_must_be_answered(): void
    {
        $this->openPackageWith(self::CATALOG_PACKAGE);

        foreach ([null, '', 'maybe'] as $launch) {
            $input = $this->valid();
            $launch === null ? $input = array_diff_key($input, ['launch' => 1]) : $input['launch'] = $launch;

            $this->post('/install/import/choose', $input)->assertRedirect()->assertSessionHasErrors('launch');
        }

        $this->assertSame([], $this->wizard()->choices(), 'nothing was saved');
        $this->get('/install/import/choose')->assertSee('Say whether this is your real, live site.');
    }

    #[Test]
    public function something_must_be_chosen_to_bring(): void
    {
        $this->openPackageWith(self::CATALOG_PACKAGE);

        foreach ([null, '', 'preset:full_live', 'preset:bogus', 'everything'] as $selection) {
            $input = $this->valid();
            $selection === null ? $input = array_diff_key($input, ['selection' => 1]) : $input['selection'] = $selection;

            $this->post('/install/import/choose', $input)->assertSessionHasErrors('selection');
        }

        $this->assertSame([], $this->wizard()->choices());
    }

    #[Test]
    public function a_part_the_package_does_not_hold_is_refused_even_if_the_form_was_tampered_with(): void
    {
        $this->openPackageWith(self::CATALOG_PACKAGE);

        foreach ([['customers'], ['bogus'], ['system'], ['catalog', 'sales']] as $groups) {
            $this->post('/install/import/choose', $this->valid(['selection' => 'custom', 'groups' => $groups]))->assertSessionHasErrors('groups');
        }

        $this->assertSame([], $this->wizard()->choices());
    }

    /** @return array<string, array{0: string}> */
    public static function badAddresses(): array
    {
        return [
            'empty' => [''],
            'no scheme' => ['shop.example'],
            'ftp' => ['ftp://shop.example'],
            'a folder' => ['http://shop.example/shop'],
            'a user name' => ['http://user:pass@shop.example'],
            'a query' => ['http://shop.example?x=1'],
            'a fragment' => ['http://shop.example#top'],
            'an underscore host' => ['http://-bad-.example'],
            'javascript' => ['javascript:alert(1)'],
            'spaces' => ['http://shop example'],
        ];
    }

    #[Test]
    #[DataProvider('badAddresses')]
    public function an_address_that_would_break_the_site_is_refused(string $address): void
    {
        $this->openPackageWith(self::CATALOG_PACKAGE);

        $this->post('/install/import/choose', $this->valid(['site_url' => $address]))->assertSessionHasErrors('site_url');

        $this->assertSame([], $this->wizard()->choices());
    }

    #[Test]
    public function the_address_is_tidied_to_scheme_host_and_port(): void
    {
        $this->openPackageWith(self::CATALOG_PACKAGE);

        $this->post('/install/import/choose', $this->valid(['site_url' => '  HTTP://Shop.Example:8080/  ']))->assertRedirect(route('installer.import.choose'));

        $this->assertSame('http://shop.example:8080', $this->wizard()->choices()['url']);
    }

    #[Test]
    public function an_https_address_is_refused_over_plain_http_and_accepted_over_https(): void
    {
        $this->openPackageWith(self::CATALOG_PACKAGE);

        $this->post('/install/import/choose', $this->valid(['site_url' => 'https://shop.example']))->assertSessionHasErrors('site_url');
        $this->get('/install/import/choose')->assertSee('would lock you out');
        $this->assertSame([], $this->wizard()->choices());

        $this->callAsBrowser('POST', 'https://shop.example/install/import/choose', $this->valid(['site_url' => 'https://shop.example']), ['HTTPS' => 'on'])
            ->assertRedirect();
        $this->assertSame('https://shop.example', $this->wizard()->choices()['url']);
    }

    #[Test]
    public function a_wrong_entry_in_the_form_keeps_what_the_person_typed(): void
    {
        $this->openPackageWith(self::CATALOG_PACKAGE);

        $this->post('/install/import/choose', $this->valid(['site_url' => 'nonsense', 'launch' => 'staging']))->assertRedirect();

        $page = $this->get('/install/import/choose');
        $page->assertSee('value="nonsense"', false);
        $this->assertMatchesRegularExpression('/name="launch" value="staging"[^>]*checked/', $page->getContent());
    }

    #[Test]
    public function the_step_has_no_upload_field_and_no_passphrase_field(): void
    {
        $this->openPackageWith(self::CATALOG_PACKAGE);

        $page = $this->get('/install/import/choose');
        $page->assertDontSee('type="file"', false);
        $page->assertDontSee('name="passphrase"', false);
    }
}
