<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use App\Services\Migration\Rewrite\DomainRewriter;
use App\Services\Migration\Rewrite\RewriteCursor;
use App\Services\Migration\Rewrite\RewritePlan;
use App\Support\AtomicJsonFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\PlantsRows;
use Tests\TestCase;

/**
 * Phase 5.3: the database rewrite. It must change what it should, leave history and mailboxes alone, never fire a
 * model event, never overwrite a concurrent edit, and end in the same place whether it ran in one go or was stopped
 * and resumed a dozen times.
 */
class DomainRewriterTest extends TestCase
{
    use PlantsRows;
    use RefreshDatabase;

    private string $cursorFile = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->cursorFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-rewrite-'.getmypid().'-'.uniqid().'.json';
    }

    protected function tearDown(): void
    {
        foreach ([$this->cursorFile, $this->cursorFile.'.prev', $this->cursorFile.'.lock'] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function plan(): RewritePlan
    {
        return new RewritePlan(['old.example'], 'https://new.example');
    }

    private function setting(string $group, string $key, string $value, string $type = 'string', bool $encrypted = false): int
    {
        return (int) Setting::create(['group' => $group, 'key' => $key, 'value' => $value, 'type' => $type, 'is_encrypted' => $encrypted])->id;
    }

    private function settingValue(string $group, string $key): ?string
    {
        return Setting::where('group', $group)->where('key', $key)->value('value');
    }

    /** One of everything the rewrite has an opinion about. */
    private function plantEverything(): void
    {
        $this->setting('general', 'site_url', 'http://old.example');
        $this->setting('general', 'site_email', 'info@old.example');
        $this->setting('seo', 'canonical_host', 'old.example');
        $this->setting('seo', 'gsc_property_url', 'https://old.example/');
        $this->setting('general', 'header_scripts', '<script src="https://old.example/t.js"></script>');
        $this->setting('appearance', 'custom_css', '.hero{background:url(https://old.example/h.png)}');
        $this->setting('social_links', 'facebook_url', 'https://old.example/fb');
        $this->setting('crawlers', 'llms_txt_body', json_encode(['en' => 'Home: https://old.example/en/ and mail info@old.example']), 'json');
        $this->setting('footer', 'note', 'Visit http://www.old.example/about today');
        $this->setting('payment', 'airwallex_api_key', 'https://old.example/never-touched', 'encrypted', true);
        $this->setting('tax', 'default_vat_rate', '21', 'integer');

        $this->plantRow('redirects', ['from_url' => '/old-path', 'to_url' => 'https://old.example/new-path']);
        $this->plantRow('redirects', ['from_url' => '/other', 'to_url' => 'https://elsewhere.example/x']);
        $this->plantRow('pages', ['slug' => 'about', 'content' => json_encode(['en' => '<a href="https://old.example/en/p">p</a> info@old.example', 'de' => '<img src="//old.example/a.png">']), 'title' => json_encode(['en' => 'About'])]);
        $this->plantRow('newsletter_campaigns', ['subject' => 'Draft', 'html_content' => '<a href="http://old.example/x">x</a>', 'plain_content' => 'http://old.example/x', 'status' => 'draft']);
        $this->plantRow('newsletter_campaigns', ['subject' => 'Sent', 'html_content' => '<a href="http://old.example/x">x</a>', 'plain_content' => 'http://old.example/x', 'status' => 'sent']);

        // History that must stay exactly as it was written.
        $orderId = $this->plantRow('orders', ['customer_note' => 'see https://old.example/page']);
        $this->plantRow('payments', ['order_id' => $orderId, 'gateway_response' => json_encode(['return_url' => 'https://old.example/thanks'])]);
        $this->plantRow('order_notes', ['order_id' => $orderId, 'note' => 'told them about https://old.example/policy']);
    }

    /** @return array<string, mixed> everything that matters, for comparing two final states */
    private function snapshot(): array
    {
        return [
            'settings' => DB::table('settings')->orderBy('id')->get(['group', 'key', 'value'])->map(fn ($r): array => (array) $r)->all(),
            'redirects' => DB::table('redirects')->orderBy('id')->get(['from_url', 'to_url'])->map(fn ($r): array => (array) $r)->all(),
            'pages' => DB::table('pages')->orderBy('id')->pluck('content')->all(),
            'campaigns' => DB::table('newsletter_campaigns')->orderBy('id')->get(['html_content', 'plain_content'])->map(fn ($r): array => (array) $r)->all(),
            'products' => DB::table('products')->orderBy('id')->pluck('video_url')->all(),
        ];
    }

    // ---- what it changes and what it must not ------------------------------------------------------------

    #[Test]
    public function it_rewrites_what_should_change_and_leaves_history_mailboxes_scripts_and_secrets_alone(): void
    {
        $this->plantEverything();
        $plan = $this->plan();

        $cursor = (new DomainRewriter)->preview($plan);
        $this->assertTrue($cursor->done);
        $cursor = RewriteCursor::fresh($plan);
        (new DomainRewriter)->advance($plan, $cursor, PHP_FLOAT_MAX);

        $this->assertTrue($cursor->done);
        $this->assertSame('https://new.example', $this->settingValue('general', 'site_url'), 'the site address is set');
        $this->assertSame('info@old.example', $this->settingValue('general', 'site_email'), 'a mailbox is never rewritten');
        $this->assertSame('old.example', $this->settingValue('seo', 'canonical_host'), 'the canonical host has a policy of its own');
        $this->assertSame('', $this->settingValue('seo', 'gsc_property_url'), 'the Search Console property is cleared');
        $this->assertStringContainsString('https://old.example/t.js', (string) $this->settingValue('general', 'header_scripts'), 'scripts are reported, not edited');
        $this->assertSame('.hero{background:url(https://new.example/h.png)}', $this->settingValue('appearance', 'custom_css'));
        $this->assertSame('https://new.example/fb', $this->settingValue('social_links', 'facebook_url'));
        $this->assertSame('Visit https://new.example/about today', $this->settingValue('footer', 'note'), 'an unlisted text setting is handled by its type');
        $this->assertSame('https://old.example/never-touched', DB::table('settings')->where('key', 'airwallex_api_key')->value('value'), 'an encrypted row is never read as text');
        $this->assertSame('21', $this->settingValue('tax', 'default_vat_rate'));

        $llms = json_decode((string) $this->settingValue('crawlers', 'llms_txt_body'), true);
        $this->assertSame('Home: https://new.example/en/ and mail info@old.example', $llms['en'], 'JSON setting rewritten, the mailbox inside it kept');

        $this->assertSame('https://new.example/new-path', DB::table('redirects')->where('from_url', '/old-path')->value('to_url'));
        $this->assertSame('https://elsewhere.example/x', DB::table('redirects')->where('from_url', '/other')->value('to_url'));
        $this->assertSame('/old-path', DB::table('redirects')->where('to_url', 'https://new.example/new-path')->value('from_url'), 'the path being redirected from is never touched');

        $page = json_decode((string) DB::table('pages')->value('content'), true);
        $this->assertSame('<a href="https://new.example/en/p">p</a> info@old.example', $page['en']);
        $this->assertSame('<img src="//new.example/a.png">', $page['de']);

        $this->assertStringContainsString('https://new.example/x', (string) DB::table('newsletter_campaigns')->where('subject', 'Draft')->value('html_content'));
        $this->assertStringContainsString('http://old.example/x', (string) DB::table('newsletter_campaigns')->where('subject', 'Sent')->value('html_content'), 'a campaign that was sent is a record of what customers received');

        $this->assertSame('see https://old.example/page', DB::table('orders')->value('customer_note'));
        $this->assertStringContainsString('old.example', (string) DB::table('payments')->value('gateway_response'));
        $this->assertStringNotContainsString('new.example', (string) DB::table('payments')->value('gateway_response'));
        $this->assertSame('told them about https://old.example/policy', DB::table('order_notes')->value('note'));
    }

    #[Test]
    public function a_second_run_changes_nothing_and_counts_zero(): void
    {
        $this->plantEverything();
        $plan = $this->plan();
        (new DomainRewriter)->advance($plan, RewriteCursor::fresh($plan), PHP_FLOAT_MAX);
        $after = $this->snapshot();

        $second = RewriteCursor::fresh($plan);
        (new DomainRewriter)->advance($plan, $second, PHP_FLOAT_MAX);

        $this->assertSame($after, $this->snapshot());
        $this->assertSame(0, $second->total('replacements'));
        $this->assertSame(0, $second->total('rows_changed'), 'nothing is left to change, not even the site address (already set)');
    }

    // ---- dry run ----------------------------------------------------------------------------------------------------

    #[Test]
    public function a_dry_run_writes_nothing_and_reports_exactly_what_the_real_run_then_changes(): void
    {
        $this->plantEverything();
        $plan = $this->plan();
        $before = $this->snapshot();

        $preview = (new DomainRewriter)->preview($plan);

        $this->assertSame($before, $this->snapshot(), 'a dry run writes nothing');

        $real = RewriteCursor::fresh($plan);
        (new DomainRewriter)->advance($plan, $real, PHP_FLOAT_MAX);

        $this->assertSame($preview->stats, $real->stats, 'the counts of the dry run are the counts of the apply run');
        $this->assertGreaterThan(5, $real->total('rows_changed'));
        $this->assertGreaterThan(5, $real->total('replacements'));
    }

    // ---- resume -----------------------------------------------------------------------------------------------------

    #[Test]
    public function a_run_stopped_after_every_chunk_and_resumed_from_its_file_ends_exactly_like_an_uninterrupted_one(): void
    {
        $this->plantEverything();
        foreach (range(1, 25) as $i) {
            $this->plantRow('products', ['video_url' => "https://old.example/v/{$i}", 'description' => json_encode(['en' => "see https://old.example/p/{$i}"])]);
        }
        $plan = $this->plan();
        $original = $this->rawRows();

        // Stopped and resumed: a brand new rewriter and a cursor read back from its file for EVERY chunk, tiny chunks.
        $file = new AtomicJsonFile($this->cursorFile);
        RewriteCursor::fresh($plan)->save($file);
        $chunks = 0;
        do {
            $cursor = RewriteCursor::load($file, $plan);
            (new DomainRewriter(chunkRows: 3))->advance($plan, $cursor, 0.0, false, fn (RewriteCursor $c) => $c->save($file));
            $this->assertLessThan(500, ++$chunks);
        } while (! RewriteCursor::load($file, $plan)->done);
        $resumed = RewriteCursor::load($file, $plan);
        $resumedState = $this->rawRows();
        $this->assertGreaterThan(15, $chunks, 'it really was interrupted many times');
        $this->assertNotSame($original, $resumedState, 'and it did rewrite');

        // The original data back, then one uninterrupted run.
        $this->putRawRows($original);
        $once = RewriteCursor::fresh($plan);
        (new DomainRewriter(chunkRows: 1000))->advance($plan, $once, PHP_FLOAT_MAX);

        $this->assertSame($resumedState, $this->rawRows());
        $this->assertSame($once->stats, $resumed->stats, 'the totals of an interrupted run are the totals of an uninterrupted one');
        $this->assertSame(25, $resumed->stats['products']['rows_changed']);
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function rawRows(): array
    {
        $rows = [];

        foreach (['settings', 'redirects', 'pages', 'newsletter_campaigns', 'products'] as $table) {
            $rows[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($r): array => (array) $r)->all();
        }

        return $rows;
    }

    /** @param  array<string, array<int, array<string, mixed>>>  $rows */
    private function putRawRows(array $rows): void
    {
        foreach ($rows as $table => $list) {
            foreach ($list as $row) {
                DB::table($table)->where('id', $row['id'])->update($row);
            }
        }
    }

    #[Test]
    public function a_cursor_made_under_another_plan_is_never_continued(): void
    {
        $file = new AtomicJsonFile($this->cursorFile);
        $cursor = RewriteCursor::fresh($this->plan());
        $cursor->step = 4;
        $cursor->save($file);

        $other = new RewritePlan(['another.example'], 'https://new.example');

        $this->assertSame(0, RewriteCursor::load($file, $other)->step, 'a different plan starts again');
        $this->assertSame(4, RewriteCursor::load($file, $this->plan())->step);

        $this->expectException(\LogicException::class);
        (new DomainRewriter)->advance($other, $cursor, 1.0);
    }

    // ---- a concurrent edit ------------------------------------------------------------------------------------------

    #[Test]
    public function a_row_edited_while_it_was_being_rewritten_is_not_overwritten_and_a_later_pass_gets_it(): void
    {
        $id = $this->plantRow('redirects', ['from_url' => '/a', 'to_url' => 'https://old.example/original']);
        $plan = $this->plan();

        $rewriter = new DomainRewriter;
        $edited = false;
        $rewriter->beforeWrite = function (string $table, mixed $rowId) use ($id, &$edited): void {
            if ($table === 'redirects' && (int) $rowId === $id && ! $edited) {
                $edited = true;
                DB::table('redirects')->where('id', $id)->update(['to_url' => 'https://old.example/edited-by-someone-else']);
            }
        };

        $first = RewriteCursor::fresh($plan);
        $rewriter->advance($plan, $first, PHP_FLOAT_MAX);

        $this->assertSame('https://old.example/edited-by-someone-else', DB::table('redirects')->where('id', $id)->value('to_url'), 'the concurrent edit survived');
        $this->assertSame(1, $first->stats['redirects']['concurrent_skips']);
        $this->assertSame(0, $first->stats['redirects']['rows_changed'] ?? 0, 'a skipped row is not counted as changed');

        $second = RewriteCursor::fresh($plan);
        (new DomainRewriter)->advance($plan, $second, PHP_FLOAT_MAX);

        $this->assertSame('https://new.example/edited-by-someone-else', DB::table('redirects')->where('id', $id)->value('to_url'), 'the next pass rewrites the new content');
    }

    // ---- no side effects --------------------------------------------------------------------------------------------

    #[Test]
    public function no_model_event_fires_and_nothing_is_queued_while_thousands_of_links_are_rewritten(): void
    {
        foreach (range(1, 30) as $i) {
            $this->plantRow('products', ['video_url' => "https://old.example/v/{$i}"]);
        }

        $fired = 0;
        Product::saving(function () use (&$fired): void {
            $fired++;
        });
        Product::saved(function () use (&$fired): void {
            $fired++;
        });
        Product::updating(function () use (&$fired): void {
            $fired++;
        });
        Product::updated(function () use (&$fired): void {
            $fired++;
        });
        Queue::fake();
        Event::fake([MessageSending::class]);
        $activityBefore = DB::table('activity_logs')->count();

        $plan = $this->plan();
        $cursor = RewriteCursor::fresh($plan);
        (new DomainRewriter(chunkRows: 7))->advance($plan, $cursor, PHP_FLOAT_MAX);

        $this->assertSame(30, $cursor->stats['products']['rows_changed']);
        $this->assertSame(0, $fired, 'an observer would have logged, busted caches or pushed IndexNow for every row');
        Queue::assertNothingPushed();
        $this->assertSame($activityBefore, DB::table('activity_logs')->count());
        $this->assertSame(0, DB::table('products')->where('video_url', 'like', '%old.example%')->count());
    }

    // ---- reading only what it needs --------------------------------------------------------------------------------

    #[Test]
    public function rows_that_do_not_mention_the_old_name_are_never_read_into_php(): void
    {
        foreach (range(1, 40) as $i) {
            $this->plantRow('products', ['video_url' => "https://elsewhere.example/v/{$i}"]);
        }
        $this->plantRow('products', ['video_url' => 'https://old.example/the-only-one']);

        $plan = $this->plan();
        $cursor = RewriteCursor::fresh($plan);
        (new DomainRewriter)->advance($plan, $cursor, PHP_FLOAT_MAX);

        $this->assertSame(1, $cursor->stats['products']['rows_seen'], 'only the row that mentions the old name was read');
        $this->assertSame(1, $cursor->stats['products']['rows_changed']);
    }

    // ---- JSON that cannot be rewritten safely ------------------------------------------------------------------------

    #[Test]
    public function json_where_the_address_sits_in_a_key_is_left_alone_and_counted(): void
    {
        $this->plantRow('pages', ['slug' => 'weird', 'title' => json_encode(['en' => 'x']), 'content' => '{"https://old.example/a":"x"}']);

        $plan = $this->plan();
        $cursor = RewriteCursor::fresh($plan);
        (new DomainRewriter)->advance($plan, $cursor, PHP_FLOAT_MAX);

        $this->assertSame('{"https://old.example/a":"x"}', DB::table('pages')->value('content'));
        $this->assertSame(1, $cursor->stats['pages']['invalid_json']);
        $this->assertSame(0, $cursor->stats['pages']['rows_changed'] ?? 0);
    }

    #[Test]
    public function a_chunk_is_always_processed_even_with_no_time_budget(): void
    {
        $this->setting('general', 'site_url', 'http://old.example');
        $plan = $this->plan();
        $cursor = RewriteCursor::fresh($plan);

        (new DomainRewriter)->advance($plan, $cursor, 0.0);

        $this->assertTrue($cursor->step >= 1 || $cursor->lastId > 0, 'a zero budget still moves');
    }
}
