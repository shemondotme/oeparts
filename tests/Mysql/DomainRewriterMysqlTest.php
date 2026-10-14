<?php

namespace Tests\Mysql;

use App\Services\Migration\Rewrite\DomainRewriter;
use App\Services\Migration\Rewrite\RewriteCursor;
use App\Services\Migration\Rewrite\RewritePlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

/**
 * Phase 5.3 on a real server. SQLite stores a JSON column as text, so it cannot show the two things MySQL does
 * differently: it reads JSON back in its own normalised form (never the text that was written), and it compares a JSON
 * column with a string as a JSON STRING (never equal to the document). The optimistic guard has to cast, and this is the
 * only place that is proven.
 */
class DomainRewriterMysqlTest extends MysqlTestCase
{
    /** @var array<int, int> */
    private array $faqs = [];

    protected function tearDown(): void
    {
        if ($this->faqs !== []) {
            DB::table('faqs')->whereIn('id', $this->faqs)->delete();
        }

        parent::tearDown();
    }

    private function plan(): RewritePlan
    {
        return new RewritePlan(['old.example'], 'https://new.example');
    }

    private function faq(array $question, array $answer): int
    {
        $row = ['question' => json_encode($question), 'answer' => json_encode($answer), 'created_at' => now(), 'updated_at' => now()];

        foreach (Schema::getColumns('faqs') as $column) {
            if (! $column['nullable'] && $column['default'] === null && empty($column['auto_increment']) && ! array_key_exists($column['name'], $row)) {
                $row[$column['name']] = in_array($column['type_name'], ['tinyint', 'int', 'bigint', 'smallint'], true) ? 1 : 'x';
            }
        }

        $id = (int) DB::table('faqs')->insertGetId($row);
        $this->faqs[] = $id;

        return $id;
    }

    #[Test]
    public function a_real_json_column_is_found_rewritten_and_stays_valid_json(): void
    {
        $this->assertSame('json', collect(Schema::getColumns('faqs'))->firstWhere('name', 'answer')['type_name'], 'this test needs a real JSON column (MySQL, not MariaDB)');
        $id = $this->faq(['en' => 'Q'], ['en' => 'Read https://old.example/en/help and write info@old.example', 'de' => '<img src="//old.example/a.png">']);

        $cursor = RewriteCursor::fresh($this->plan());
        (new DomainRewriter)->advance($this->plan(), $cursor, PHP_FLOAT_MAX);

        $answer = json_decode((string) DB::table('faqs')->where('id', $id)->value('answer'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Read https://new.example/en/help and write info@old.example', $answer['en']);
        $this->assertSame('<img src="//new.example/a.png">', $answer['de']);
        $this->assertGreaterThanOrEqual(1, $cursor->stats['faqs']['rows_changed']);
    }

    #[Test]
    public function the_guard_on_a_json_column_matches_the_document_it_read_so_the_write_goes_through(): void
    {
        $id = $this->faq(['en' => 'Q'], ['en' => 'https://old.example/x']);

        $cursor = RewriteCursor::fresh($this->plan());
        (new DomainRewriter)->advance($this->plan(), $cursor, PHP_FLOAT_MAX);

        $this->assertSame(0, $cursor->stats['faqs']['concurrent_skips'] ?? 0, 'a plain string comparison would never match a JSON column and skip every row');
        $this->assertSame('https://new.example/x', json_decode((string) DB::table('faqs')->where('id', $id)->value('answer'), true)['en']);
    }

    #[Test]
    public function a_json_row_edited_meanwhile_is_not_overwritten(): void
    {
        $id = $this->faq(['en' => 'Q'], ['en' => 'https://old.example/original']);
        $rewriter = new DomainRewriter;
        $edited = false;
        $rewriter->beforeWrite = function (string $table, mixed $rowId) use ($id, &$edited): void {
            if ($table === 'faqs' && (int) $rowId === $id && ! $edited) {
                $edited = true;
                DB::table('faqs')->where('id', $id)->update(['answer' => json_encode(['en' => 'https://old.example/edited-meanwhile'])]);
            }
        };

        $cursor = RewriteCursor::fresh($this->plan());
        $rewriter->advance($this->plan(), $cursor, PHP_FLOAT_MAX);

        $this->assertSame('https://old.example/edited-meanwhile', json_decode((string) DB::table('faqs')->where('id', $id)->value('answer'), true)['en']);
        $this->assertSame(1, $cursor->stats['faqs']['concurrent_skips']);

        $second = RewriteCursor::fresh($this->plan());
        (new DomainRewriter)->advance($this->plan(), $second, PHP_FLOAT_MAX);
        $this->assertSame('https://new.example/edited-meanwhile', json_decode((string) DB::table('faqs')->where('id', $id)->value('answer'), true)['en']);
    }

    #[Test]
    public function mysql_reads_json_back_without_escaped_slashes_and_the_rewrite_does_not_mind(): void
    {
        // Written with escaped slashes; MySQL stores the document and returns it normalised.
        $id = $this->faq(['en' => 'Q'], ['en' => 'x']);
        DB::statement('UPDATE faqs SET answer = ? WHERE id = ?', ['{"en": "https:\/\/old.example\/escaped"}', $id]);

        $cursor = RewriteCursor::fresh($this->plan());
        (new DomainRewriter)->advance($this->plan(), $cursor, PHP_FLOAT_MAX);

        $this->assertSame('https://new.example/escaped', json_decode((string) DB::table('faqs')->where('id', $id)->value('answer'), true)['en']);
    }
}
