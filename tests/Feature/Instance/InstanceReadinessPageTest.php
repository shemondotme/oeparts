<?php

namespace Tests\Feature\Instance;

use App\Filament\Pages\System\InstanceReadiness;
use App\Filament\Widgets\Instance\MaskedSettingsTable;
use App\Filament\Widgets\Instance\ReadinessChecksTable;
use App\Services\Instance\InstanceState;
use App\Services\Instance\SafeMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Instance\Concerns\ArrangesInstanceSite;
use Tests\Support\PlantsRows;
use Tests\TestCase;

/**
 * Phase 10.5: System > Site mode, first part: who may open it, the overview, running the checks, the Safe mode tab and the mail allow-list.
 * The page decides nothing (the instance services do), so these tests drive it the way a browser would and check what it shows.
 */
class InstanceReadinessPageTest extends TestCase
{
    use ArrangesInstanceSite;
    use PlantsRows;
    use RefreshDatabase;

    // ---- who may open it --------------------------------------------------------------------------------------------------

    #[Test]
    public function a_super_admin_can_open_the_page(): void
    {
        $this->arrange('production');
        config(['app.url' => 'http://localhost']); // the canonical-host middleware would send an http request to an https APP_URL elsewhere

        $this->get(InstanceReadiness::getUrl())->assertOk()->assertSee('Site mode');
    }

    #[Test]
    public function a_support_role_cannot_open_the_page(): void
    {
        $this->arrange('production');
        config(['app.url' => 'http://localhost']);
        $this->actingAs($this->makeAdmin('support'), 'admin');

        $this->assertFalse(InstanceReadiness::canAccess());
        $this->get(InstanceReadiness::getUrl())->assertForbidden();
    }

    #[Test]
    public function the_widgets_refuse_to_render_for_someone_who_cannot_open_the_page(): void
    {
        $this->actingAs($this->makeAdmin('support'), 'admin');

        $this->assertFalse(ReadinessChecksTable::canView());
        $this->assertFalse(MaskedSettingsTable::canView());
    }

    #[Test]
    public function the_page_is_in_the_system_group_and_a_copy_shows_its_mode_in_the_sidebar(): void
    {
        $this->assertSame('System', InstanceReadiness::getNavigationGroup());
        $this->assertSame('system/site-mode', (new \ReflectionClass(InstanceReadiness::class))->getStaticPropertyValue('slug'));

        config(['instance.mode' => 'production']);
        $this->assertNull(InstanceReadiness::getNavigationBadge());

        config(['instance.mode' => 'staging']);
        $this->assertSame('STAGING', InstanceReadiness::getNavigationBadge());
        $this->assertSame('warning', InstanceReadiness::getNavigationBadgeColor());

        config(['instance.mode' => 'quarantine']);
        $this->assertSame('QUARANTINE', InstanceReadiness::getNavigationBadge());
        $this->assertSame('danger', InstanceReadiness::getNavigationBadgeColor());
    }

    // ---- overview ---------------------------------------------------------------------------------------------------------

    #[Test]
    public function the_overview_says_what_the_site_is_in_each_mode_and_what_to_do_next(): void
    {
        $this->arrange('production');
        Livewire::test(InstanceReadiness::class)
            ->assertSee('Production')
            ->assertSee('This is the real shop')
            ->assertSee('Nothing to do here')
            ->assertSee('shop.example')
            ->assertSee('iabc123');

        $this->arrange('staging');
        Livewire::test(InstanceReadiness::class)
            ->assertSee('Staging')
            ->assertSee('This is a testing copy')
            ->assertSee('Go live');

        $this->arrange('quarantine');
        Livewire::test(InstanceReadiness::class)
            ->assertSee('Quarantine')
            ->assertSee('not trusted yet')
            ->assertSee('Release from quarantine');
    }

    #[Test]
    public function a_mode_that_is_not_a_mode_is_called_out_and_the_site_is_run_as_quarantine(): void
    {
        $this->arrange('stagging');

        Livewire::test(InstanceReadiness::class)
            ->assertSee('The mode in .env is not valid')
            ->assertSee('stagging')
            ->assertSee('Quarantine');
    }

    #[Test]
    public function a_ledger_that_disagrees_with_env_is_shown_with_env_winning(): void
    {
        $this->arrange('production');
        (new InstanceState)->update(function (array $ledger): array {
            $ledger['mode_history'][] = ['at' => now()->toIso8601String(), 'by' => 'test', 'from' => 'quarantine', 'to' => 'staging'];

            return $ledger;
        });

        Livewire::test(InstanceReadiness::class)
            ->assertSee('The record and .env disagree')
            ->assertSee('last recorded mode staging');
    }

    #[Test]
    public function recent_env_changes_show_who_and_which_keys_but_never_a_value(): void
    {
        $this->arrange('staging');
        (new InstanceState)->recordEnvChange('admin #7', ['OE_MAIL_ALLOWLIST' => ['from' => '', 'to' => 'secret-tester@example.com']]);

        Livewire::test(InstanceReadiness::class)
            ->assertSee('admin #7')
            ->assertSee('OE_MAIL_ALLOWLIST')
            ->assertDontSee('secret-tester@example.com');
    }

    // ---- running the checks -----------------------------------------------------------------------------------------------

    #[Test]
    public function before_any_run_the_page_says_the_checks_have_not_been_run(): void
    {
        $this->arrange('quarantine');

        Livewire::test(InstanceReadiness::class)->assertSee('The checks have not been run on this site yet');
        Livewire::test(ReadinessChecksTable::class)->assertSee('No checks have run yet');
    }

    #[Test]
    public function run_checks_does_one_check_at_once_and_the_poll_finishes_the_rest(): void
    {
        $this->arrange('quarantine', [$this->probe('one', 'pass'), $this->probe('two', 'pass'), $this->probe('three', 'pass')]);

        $page = Livewire::test(InstanceReadiness::class)
            ->callAction('runChecks')
            ->assertNotified('Checks started')
            ->assertSet('checking', true)
            ->assertSee('Checking the site')
            ->assertSee('1 of 3 checks done')
            ->assertSee('33%')
            ->assertSee('Label of one');

        $page->call('advanceChecks')
            ->assertSet('checking', false)
            ->assertNotified('The checks are done')
            ->assertDontSee('Checking the site')
            ->assertSee('3 passed')
            ->assertSee('Nothing blocks the launch');

        $saved = json_decode((string) file_get_contents($this->dir.'/readiness.json'), true);
        $this->assertTrue($saved['complete']);
        $this->assertCount(3, $saved['checks']);
    }

    #[Test]
    public function the_poll_does_nothing_when_no_run_was_started_here(): void
    {
        $this->arrange('quarantine', [$this->probe('one', 'pass'), $this->probe('two', 'pass')]);

        Livewire::test(InstanceReadiness::class)->call('advanceChecks')->assertSet('checking', false);

        $this->assertFileDoesNotExist($this->dir.'/readiness.json', 'a poll that was never asked to run must not start a run');
    }

    #[Test]
    public function a_run_that_ends_with_a_failure_says_something_blocks_the_launch(): void
    {
        $this->arrange('quarantine', [$this->probe('rows_vs_manifest', 'fail', false, 'data', 'Counts differ.', 'Import again.')]);

        $this->pageAfterChecks()
            ->assertNotified('The checks are done')
            ->assertSee('1 failed')
            ->assertSee('Something blocks the launch');
    }

    #[Test]
    public function a_report_that_is_not_finished_is_shown_as_unfinished_and_cannot_be_trusted(): void
    {
        $this->arrange('quarantine', [$this->probe('one', 'pass'), $this->probe('two', 'pass')]);

        $page = Livewire::test(InstanceReadiness::class)->callAction('runChecks')->assertSet('checking', true);
        $page->assertSee('The last run did not finish');
        $page->assertDontSee('Nothing blocks the launch');
    }

    #[Test]
    public function the_table_lists_the_worst_first_with_what_to_do_and_the_waiver_note(): void
    {
        $this->arrange('quarantine', [
            $this->probe('good_one', 'pass', false, 'data', 'All fine here.'),
            $this->probe('storage_link', 'fail', true, 'runtime', 'The link is missing.', 'Run storage:link.'),
            $this->probe('needs_time', 'pending', false, 'runtime', 'Not known yet.', 'Wait for the next tick.'),
            $this->probe('odd_one', 'warn', false, 'identity', 'Looks odd.', 'Have a look.'),
        ]);
        $this->pageAfterChecks();

        $component = Livewire::test(ReadinessChecksTable::class)
            ->assertSee('Label of storage_link')
            ->assertSee('The link is missing.')
            ->assertSee('Run storage:link.')
            ->assertSee('Can be waived with a reason.')
            ->assertSee('Wait for the next tick.')
            ->assertSee('Have a look.')
            ->assertSee('Running site')
            ->assertSee('Identity')
            ->assertSee('Data');

        $html = $component->html();
        $order = array_map(static fn (string $needle): int => (int) strpos($html, $needle), ['Label of storage_link', 'Label of needs_time', 'Label of odd_one', 'Label of good_one']);
        $sorted = $order;
        sort($sorted);
        $this->assertSame($sorted, $order, 'failures first, then waiting, then warnings, then passes');
    }

    #[Test]
    public function a_check_that_passed_shows_no_what_to_do(): void
    {
        $this->arrange('quarantine', [$this->probe('good_one', 'pass', false, 'data', 'All fine here.', 'This fix text must stay hidden.')]);
        $this->pageAfterChecks();

        Livewire::test(ReadinessChecksTable::class)->assertSee('All fine here.')->assertDontSee('This fix text must stay hidden.');
    }

    // ---- safe mode --------------------------------------------------------------------------------------------------------

    #[Test]
    public function the_real_shop_says_safe_mode_holds_nothing_back_and_offers_no_allow_list(): void
    {
        $this->arrange('production');

        Livewire::test(InstanceReadiness::class)
            ->assertSee('Safe mode holds nothing back')
            ->assertDontSee('Mail allow-list')
            ->assertDontSee('What this copy holds back');
        Livewire::test(MaskedSettingsTable::class)->assertSee('Nothing is masked');
    }

    #[Test]
    public function a_testing_copy_lists_what_it_holds_back(): void
    {
        $this->arrange('staging');
        config(['instance.mail.allowlist' => 'a@example.com,@example.org']);

        Livewire::test(InstanceReadiness::class)
            ->assertSee('What this copy holds back')
            ->assertSee('dropped, except to the allow-list (2); subjects start with [STAGING]')
            ->assertSee('keeping the site healthy: runs; housekeeping: runs; reaching customers: held')
            ->assertSee('start with T-')
            ->assertSee('told to stay away')
            ->assertSee('Mail allow-list');

        Livewire::test(MaskedSettingsTable::class)->assertSee('gtm_id')->assertSee('integrations')->assertSee('(empty)');
    }

    #[Test]
    public function a_quarantined_copy_drops_all_mail_and_runs_only_the_healthy_keeping_work(): void
    {
        $this->arrange('quarantine');

        Livewire::test(InstanceReadiness::class)
            ->assertSee('Mail: dropped')
            ->assertSee('keeping the site healthy: runs; housekeeping: held; reaching customers: held');
    }

    #[Test]
    public function the_allow_list_is_saved_to_env_lower_cased_and_without_duplicates(): void
    {
        $this->arrange('staging');

        Livewire::test(InstanceReadiness::class)
            ->fillForm(['allowlist' => ['Tester@Example.com', '@example.org', 'tester@example.com']])
            ->call('saveAllowList')
            ->assertHasNoFormErrors()
            ->assertNotified('Allow-list saved');

        $this->assertStringContainsString('OE_MAIL_ALLOWLIST="tester@example.com,@example.org"', $this->envText());
        $this->assertSame('tester@example.com,@example.org', config('instance.mail.allowlist'));
        $history = $this->ledger()['env_history'];
        $this->assertSame('admin #'.$this->admin->getKey(), end($history)['by']);
    }

    #[Test]
    public function the_allow_list_form_starts_with_what_is_in_use_now(): void
    {
        $this->arrange('staging');
        config(['instance.mail.allowlist' => 'a@example.com,@example.org']);

        Livewire::test(InstanceReadiness::class)->assertFormSet(['allowlist' => ['a@example.com', '@example.org']]);
    }

    #[Test]
    public function an_entry_that_is_not_an_address_or_a_domain_is_refused_and_nothing_is_written(): void
    {
        $this->arrange('staging');

        Livewire::test(InstanceReadiness::class)
            ->fillForm(['allowlist' => ['tester@example.com', 'not an address']])
            ->call('saveAllowList')
            ->assertHasFormErrors(['allowlist.1']);

        $this->assertStringNotContainsString('OE_MAIL_ALLOWLIST', $this->envText());
    }

    #[Test]
    public function more_than_the_limit_is_refused(): void
    {
        $this->arrange('staging');
        $entries = array_map(static fn (int $i): string => "tester{$i}@example.com", range(1, InstanceReadiness::ALLOWLIST_MAX + 1));

        Livewire::test(InstanceReadiness::class)
            ->fillForm(['allowlist' => $entries])
            ->call('saveAllowList')
            ->assertHasFormErrors(['allowlist']);

        $this->assertStringNotContainsString('OE_MAIL_ALLOWLIST', $this->envText());
    }

    #[Test]
    public function an_empty_list_clears_the_allow_list_and_saving_the_same_list_changes_nothing(): void
    {
        $this->arrange('staging');
        config(['instance.mail.allowlist' => 'a@example.com']);
        file_put_contents($this->dir.'/.env', $this->envText().'OE_MAIL_ALLOWLIST="a@example.com"'."\n");

        Livewire::test(InstanceReadiness::class)
            ->fillForm(['allowlist' => ['a@example.com']])
            ->call('saveAllowList')
            ->assertNotified('Nothing changed');

        Livewire::test(InstanceReadiness::class)
            ->fillForm(['allowlist' => []])
            ->call('saveAllowList')
            ->assertNotified('Allow-list saved');

        $this->assertStringContainsString('OE_MAIL_ALLOWLIST=""', $this->envText());
        $this->assertSame([], app(SafeMode::class)->mailAllowList());
    }

    #[Test]
    public function the_real_shop_cannot_save_an_allow_list_even_by_calling_the_method(): void
    {
        $this->arrange('production');

        Livewire::test(InstanceReadiness::class)
            ->call('saveAllowList')
            ->assertNotified('The real shop delivers all mail, so there is no allow-list to edit.');

        $this->assertStringNotContainsString('OE_MAIL_ALLOWLIST', $this->envText());
    }

    #[Test]
    public function the_entry_rule_accepts_addresses_and_domains_and_nothing_else(): void
    {
        foreach (['tester@example.com', '@example.com', 'first.last+tag@sub.example.co.uk', 'A_b-c@Example.COM'] as $good) {
            $this->assertTrue(InstanceReadiness::isAllowListEntry($good), $good);
        }

        foreach (['', 'example.com', '@', '@com', 'a@b', 'a b@example.com', 'a@exam ple.com', '.a@example.com', 'a..b@example.com', "a'b@example.com", 'a$b@example.com', '@-example.com', '@example-.com', 'a@@example.com'] as $bad) {
            $this->assertFalse(InstanceReadiness::isAllowListEntry($bad), $bad);
        }
    }

    // ---- the words --------------------------------------------------------------------------------------------------------

    #[Test]
    public function every_text_the_page_asks_for_exists_in_the_language_file(): void
    {
        $files = [
            base_path('app/Filament/Pages/System/InstanceReadiness.php'),
            base_path('app/Filament/Widgets/Instance/ReadinessChecksTable.php'),
            base_path('app/Filament/Widgets/Instance/MaskedSettingsTable.php'),
        ];

        $keys = [];
        foreach ($files as $file) {
            // Only complete keys: 'instance.mode.' followed by a variable is a prefix, and is covered by the lists below.
            preg_match_all("/__\\('(instance\\.[a-z0-9_.]*[a-z0-9])'/", (string) file_get_contents($file), $found);
            $keys = array_merge($keys, $found[1]);
        }

        // Keys built from a mode, a result, an area or a reason.
        foreach (['production', 'staging', 'quarantine'] as $mode) {
            array_push($keys, "instance.mode.{$mode}", "instance.mode.{$mode}_text", "instance.overview.next_{$mode}");
        }
        foreach (['pass', 'warn', 'fail', 'pending', 'skip'] as $status) {
            $keys[] = "instance.readiness.status.{$status}";
        }
        foreach (['data', 'identity', 'secrets', 'mode', 'runtime', 'domain'] as $group) {
            $keys[] = "instance.readiness.groups.{$group}";
        }
        foreach (['not_quarantine', 'not_staging', 'host_not_typed', 'activity_present', 'payments_unconfirmed', 'waiver_refused', 'readiness_blocked', 'workers_stale', 'default'] as $reason) {
            $keys[] = "instance.blocked.{$reason}";
        }
        foreach (['yes', 'no', 'web_cron_on', 'web_cron_off'] as $word) {
            $keys[] = "instance.safe.{$word}";
        }

        $missing = [];
        foreach (array_unique($keys) as $key) {
            if (! Lang::has($key, 'en') || ! is_string(Lang::get($key, [], 'en'))) {
                $missing[] = $key;
            }
        }

        $this->assertSame([], $missing, 'texts the page asks for but the language file does not have');
        $this->assertGreaterThan(60, count(array_unique($keys)));
    }
}
