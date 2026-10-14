<?php

namespace Tests\Mysql;

use App\Services\Migration\Import\FulltextIndex;
use App\Services\Migration\Import\FulltextRollbackHook;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\Stages\DbIndexDropStage;
use App\Services\Migration\Import\Stages\DbIndexRestoreStage;
use App\Services\Migration\KeyEscrow;
use PHPUnit\Framework\Attributes\Test;

/**
 * Phase 6.6c on a real server: the ngram FULLTEXT index of the products search really leaves before the load and really
 * comes back, once, after the stage and after a rollback. SQLite has no such index, so only this suite can show it.
 */
class ImportFulltextIndexMysqlTest extends MysqlTestCase
{
    private string $runId = '';

    protected function setUp(): void
    {
        parent::setUp();

        $index = new FulltextIndex;

        if (! $index->supported()) {
            $this->markTestSkipped('This server has no ngram parser (MariaDB): the import leaves the search index alone.');
        }

        if (! $index->exists()) {
            $index->add();
        }

        config(['migration.import.fulltext_strategy' => 'drop_readd']);
        $this->runId = 'idx'.bin2hex(random_bytes(6));
        (new ImportMarker)->write($this->runId);
    }

    protected function tearDown(): void
    {
        (new ImportMarker)->remove();
        $index = new FulltextIndex;

        if ($index->supported() && ! $index->exists()) {
            $index->add();
        }

        if ($this->runId !== '') {
            exec('rm -rf '.escapeshellarg((new ImportStateStore)->directory($this->runId)));
        }

        parent::tearDown();
    }

    private function state(): ImportState
    {
        $state = new ImportState($this->runId);
        $state->setFlag('clear.done');

        return $state;
    }

    private function context(): ImportContext
    {
        return new ImportContext(microtime(true) + 600, app(KeyEscrow::class));
    }

    #[Test]
    public function the_index_is_taken_off_for_the_load_and_built_again_after(): void
    {
        $index = new FulltextIndex;
        $state = $this->state();
        $store = new ImportStateStore;

        (new DbIndexDropStage($index, new ImportMarker, $store))->step($state, $this->context());

        $this->assertFalse($index->exists(), 'the index is really gone');
        $this->assertSame([$index->name()], $state->package['indexes_dropped']);

        (new DbIndexRestoreStage($index, $store))->step($state, $this->context());

        $this->assertTrue($index->exists(), 'and really back');
        $this->assertTrue($state->flag('index.restore.done'));
    }

    #[Test]
    public function running_either_stage_twice_changes_nothing_more(): void
    {
        $index = new FulltextIndex;
        $store = new ImportStateStore;
        $state = $this->state();
        $drop = new DbIndexDropStage($index, new ImportMarker, $store);
        $restore = new DbIndexRestoreStage($index, $store);

        $drop->step($state, $this->context());
        $drop->step($state, $this->context());
        $this->assertFalse($index->exists());

        $restore->step($state, $this->context());
        $restore->step($state, $this->context());
        $this->assertTrue($index->exists());
    }

    #[Test]
    public function a_process_killed_after_the_drop_ran_but_before_it_was_flagged_resumes_without_a_second_drop(): void
    {
        $index = new FulltextIndex;
        $store = new ImportStateStore;
        $state = $this->state();
        $state->package['indexes_dropped'] = [$index->name()];
        $index->drop(); // the statement ran; the flag was never saved

        (new DbIndexDropStage($index, new ImportMarker, $store))->step($state, $this->context());

        $this->assertTrue($state->flag('index.drop.done'));
        $this->assertFalse($index->exists());
    }

    #[Test]
    public function the_rollback_puts_the_index_back(): void
    {
        $index = new FulltextIndex;
        $state = $this->state();
        (new DbIndexDropStage($index, new ImportMarker, new ImportStateStore))->step($state, $this->context());
        $this->assertFalse($index->exists());

        $this->assertTrue((new FulltextRollbackHook($index, new ImportStateStore))->run($state, $this->context()));

        $this->assertTrue($index->exists(), 'a rolled back import leaves the search as it found it');
    }

    #[Test]
    public function the_default_strategy_leaves_the_index_where_it_is(): void
    {
        config(['migration.import.fulltext_strategy' => 'keep']);
        $index = new FulltextIndex;
        $state = $this->state();

        (new DbIndexDropStage($index, new ImportMarker, new ImportStateStore))->step($state, $this->context());

        $this->assertTrue($index->exists());
        $this->assertArrayNotHasKey('indexes_dropped', $state->package);
    }
}
