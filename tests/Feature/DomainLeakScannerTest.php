<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Migration\Rewrite\DomainLeakScanner;
use App\Services\Migration\Rewrite\DomainRewriteRegistry;
use App\Services\Migration\Rewrite\RewriteKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\PlantsRows;
use Tests\TestCase;

/**
 * Phase 5.3: the leak scanner reports where the old name still is, files each place under what the registry decided,
 * flags a place nobody decided anything about, and says so when it could not finish a column instead of calling it clean.
 */
class DomainLeakScannerTest extends TestCase
{
    use PlantsRows;
    use RefreshDatabase;

    /** @return array<string, array<string, mixed>> "table.column" => entry */
    private function byColumn(array $entries): array
    {
        $out = [];

        foreach ($entries as $entry) {
            $out["{$entry['table']}.{$entry['column']}"] = $entry;
        }

        return $out;
    }

    private function plant(): void
    {
        $this->plantRow('redirects', ['from_url' => '/a', 'to_url' => 'https://old.example/x']);
        $this->plantRow('pages', ['slug' => 'p', 'title' => json_encode(['en' => 't']), 'content' => json_encode(['en' => 'see https://old.example/']), 'status' => 'published']);
        $orderId = $this->plantRow('orders', ['customer_note' => 'about https://old.example/']);
        $this->plantRow('payments', ['order_id' => $orderId, 'gateway_response' => '{"u":"https://old.example/"}']);
        $this->plantRow('invoice_bank_accounts', ['instructions' => 'pay via https://old.example/pay']);
        Setting::create(['group' => 'general', 'key' => 'site_email', 'value' => 'info@old.example', 'type' => 'string', 'is_encrypted' => false]);
        Setting::create(['group' => 'general', 'key' => 'header_scripts', 'value' => '<script src="https://old.example/t.js">', 'type' => 'string', 'is_encrypted' => false]);
        Setting::create(['group' => 'social_links', 'key' => 'facebook_url', 'value' => 'https://old.example/fb', 'type' => 'string', 'is_encrypted' => false]);
        Setting::create(['group' => 'payment', 'key' => 'airwallex_api_key', 'value' => 'https://old.example/secret', 'type' => 'encrypted', 'is_encrypted' => true]);
    }

    #[Test]
    public function every_place_that_still_mentions_the_old_name_is_filed_under_what_was_decided_about_it(): void
    {
        $this->plant();

        $report = (new DomainLeakScanner)->scan(['old.example']);
        $found = $this->byColumn($report->entries);

        $this->assertSame(DomainLeakScanner::REWRITTEN, $found['redirects.to_url']['class']);
        $this->assertSame(DomainLeakScanner::REWRITTEN, $found['pages.content']['class']);
        $this->assertSame(DomainLeakScanner::PRESERVED, $found['orders.customer_note']['class']);
        $this->assertSame(DomainLeakScanner::PRESERVED, $found['payments.gateway_response']['class']);
        $this->assertSame(DomainLeakScanner::SCAN_ONLY, $found['invoice_bank_accounts.instructions']['class']);
        $this->assertSame(DomainLeakScanner::MAILBOX, $found['settings.general.site_email']['class']);
        $this->assertSame(DomainLeakScanner::SCAN_ONLY, $found['settings.general.header_scripts']['class']);
        $this->assertSame(DomainLeakScanner::REWRITTEN, $found['settings.social_links.facebook_url']['class']);
        $this->assertArrayNotHasKey('settings.payment.airwallex_api_key', $found, 'an encrypted row is never read as text');
        $this->assertFalse($report->hasLeaks(), 'every place was decided');
        $this->assertFalse($report->needsAttention());
        $this->assertSame(1, $found['redirects.to_url']['rows']);
    }

    #[Test]
    public function a_clean_site_reports_nothing(): void
    {
        $this->plantRow('redirects', ['from_url' => '/a', 'to_url' => 'https://elsewhere.example/x']);

        $report = (new DomainLeakScanner)->scan(['old.example']);

        $this->assertSame([], $report->entries);
        $this->assertFalse($report->needsAttention());
    }

    #[Test]
    public function a_planted_place_nobody_decided_about_is_a_leak(): void
    {
        $this->plantRow('orders', ['customer_note' => 'about https://old.example/']);

        // A registry that has forgotten to decide about orders.customer_note, as one would after a new migration.
        $forgetful = new class extends DomainRewriteRegistry
        {
            public function classified(): array
            {
                $all = parent::classified();
                unset($all['orders.customer_note']);

                return $all;
            }
        };

        $report = (new DomainLeakScanner($forgetful))->scan(['old.example']);

        $this->assertTrue($report->hasLeaks());
        $this->assertSame(DomainLeakScanner::LEAK, $this->byColumn($report->entries)['orders.customer_note']['class']);
        $this->assertTrue($report->needsAttention());
        $this->assertSame(['orders.customer_note'], array_map(fn (array $e): string => $e['table'].'.'.$e['column'], $report->ofClass(DomainLeakScanner::LEAK)));
    }

    #[Test]
    public function a_column_that_ran_out_of_time_is_incomplete_never_clean(): void
    {
        $this->plantRow('redirects', ['from_url' => '/a', 'to_url' => 'https://old.example/x']);

        $slow = new class extends DomainLeakScanner
        {
            protected function countMentions(string $table, string $column, array $needles): int
            {
                if ($table === 'pages' && $column === 'content') {
                    throw new \RuntimeException('Query execution was interrupted, maximum statement execution time exceeded');
                }

                return parent::countMentions($table, $column, $needles);
            }
        };

        $report = $slow->scan(['old.example']);
        $found = $this->byColumn($report->entries);

        $this->assertTrue($found['pages.content']['incomplete']);
        $this->assertSame(1, $found['redirects.to_url']['rows'], 'the other columns were still checked');
        $this->assertTrue($report->needsAttention(), 'a site with an unchecked column cannot be called clean');
        $this->assertCount(1, $report->incomplete());
    }

    #[Test]
    public function an_error_that_is_not_a_timeout_is_not_swallowed(): void
    {
        $broken = new class extends DomainLeakScanner
        {
            protected function countMentions(string $table, string $column, array $needles): int
            {
                throw new \RuntimeException('disk on fire');
            }
        };

        $this->expectExceptionMessage('disk on fire');
        $broken->scan(['old.example']);
    }

    #[Test]
    public function on_a_big_table_only_text_sized_columns_are_asked_and_the_short_ones_are_listed_as_skipped(): void
    {
        $this->plantRow('redirects', ['from_url' => '/a', 'to_url' => 'https://old.example/x']);

        // Every table counts as big: the short to_url column must be skipped, a text-sized one must not.
        $report = (new DomainLeakScanner(bigTableRows: 0))->scan(['old.example']);

        $this->assertContains('redirects.to_url', $report->skippedShortColumns);
        $this->assertArrayNotHasKey('redirects.to_url', $this->byColumn($report->entries));
    }

    #[Test]
    public function the_scan_changes_nothing(): void
    {
        $this->plant();
        $before = [DB::table('redirects')->get()->all(), DB::table('settings')->get()->all(), DB::table('pages')->get()->all()];

        (new DomainLeakScanner)->scan(['old.example']);

        $this->assertEquals($before, [DB::table('redirects')->get()->all(), DB::table('settings')->get()->all(), DB::table('pages')->get()->all()]);
    }

    #[Test]
    public function it_suggests_the_names_the_site_is_stored_under_that_were_not_asked_about(): void
    {
        Setting::create(['group' => 'general', 'key' => 'site_url', 'value' => 'http://www.legacy.example', 'type' => 'string', 'is_encrypted' => false]);
        Setting::create(['group' => 'seo', 'key' => 'canonical_host', 'value' => 'canonical.example', 'type' => 'string', 'is_encrypted' => false]);

        $suggested = (new DomainLeakScanner)->suggestLegacyHosts(['canonical.example']);

        $this->assertContains('legacy.example', $suggested);
        $this->assertNotContains('canonical.example', $suggested, 'one that was already asked about is not suggested again');
    }

    #[Test]
    public function the_preserve_classification_is_reported_but_a_noise_column_is_not(): void
    {
        $registry = new DomainRewriteRegistry;

        $this->assertSame(RewriteKind::Ignore, $registry->classification('conditions', 'text_color')[0]);

        $this->plantRow('conditions', ['text_color' => 'old.example-looks-like-a-host']);
        $report = (new DomainLeakScanner)->scan(['old.example']);

        $this->assertArrayNotHasKey('conditions.text_color', $this->byColumn($report->entries));
    }
}
