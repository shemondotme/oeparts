<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `products` has a FULLTEXT index, so every ALTER TABLE on it copies the whole table
 * (minutes at the real ~1M rows — measured ~6 min EACH in a rehearsal). This release
 * used to issue five of them; one migration now adds everything in a single ALTER, and
 * the individual migrations that own the columns find nothing left to do. These tests
 * pin that every route — fresh, half-migrated, re-run — converges on the same schema.
 */
class ProductColumnsOnePassMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNS = ['slug', 'specifications', 'warranty_months', 'video_url'];

    private function migration(): object
    {
        return require database_path('migrations/2026_08_12_000000_add_release_2_0_product_columns_in_one_pass.php');
    }

    private function dropEverythingThisReleaseAdded(): void
    {
        Schema::table('products', function ($table) {
            if (Schema::hasIndex('products', ['slug'])) {
                $table->dropIndex(['slug']);
            }
        });
        Schema::table('products', function ($table) {
            foreach (self::COLUMNS as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    #[Test]
    public function a_fresh_database_ends_up_with_every_column_and_the_slug_index(): void
    {
        foreach (self::COLUMNS as $column) {
            $this->assertTrue(Schema::hasColumn('products', $column), "products.{$column} missing");
        }
        $this->assertTrue(Schema::hasIndex('products', ['slug']));
    }

    #[Test]
    public function it_adds_everything_when_nothing_exists_yet(): void
    {
        $this->dropEverythingThisReleaseAdded();
        $this->assertFalse(Schema::hasColumn('products', 'slug'));

        $this->migration()->up();

        foreach (self::COLUMNS as $column) {
            $this->assertTrue(Schema::hasColumn('products', $column), "products.{$column} missing");
        }
        $this->assertTrue(Schema::hasIndex('products', ['slug']));
    }

    #[Test]
    public function it_only_adds_what_an_interrupted_update_left_missing(): void
    {
        // A previous, interrupted update physically created some of it.
        $this->dropEverythingThisReleaseAdded();
        Schema::table('products', fn ($table) => $table->string('slug', 220)->nullable());
        Schema::table('products', fn ($table) => $table->unsignedSmallInteger('warranty_months')->nullable());

        $this->migration()->up();

        foreach (self::COLUMNS as $column) {
            $this->assertTrue(Schema::hasColumn('products', $column), "products.{$column} missing");
        }
        $this->assertTrue(Schema::hasIndex('products', ['slug']), 'the slug index is added even when the column already existed');
    }

    #[Test]
    public function running_it_again_on_an_already_migrated_schema_changes_nothing(): void
    {
        $this->migration()->up();
        $this->migration()->up();

        foreach (self::COLUMNS as $column) {
            $this->assertTrue(Schema::hasColumn('products', $column));
        }
    }

    #[Test]
    public function on_mysql_everything_missing_goes_into_a_single_alter_statement(): void
    {
        // Laravel's Blueprint compiles every added column into its OWN `alter table`, and each
        // one rebuilds `products` (FULLTEXT index => no INSTANT/INPLACE) — minutes apiece at 1M
        // rows. The first cut of this migration used Blueprint and saved nothing; the MySQL
        // path is one hand-written statement. Pin the exact SQL: the suite has no MySQL server.
        $sql = $this->migration()->mysqlStatement(['slug', 'specifications', 'warranty_months', 'video_url'], true);

        $this->assertSame(
            'ALTER TABLE `products` '
            .'ADD COLUMN `slug` VARCHAR(220) NULL AFTER `normalized_oem`, '
            .'ADD COLUMN `specifications` JSON NULL AFTER `description`, '
            .'ADD COLUMN `warranty_months` SMALLINT UNSIGNED NULL AFTER `moq`, '
            .'ADD COLUMN `video_url` VARCHAR(500) NULL AFTER `warranty_months`, '
            .'ADD INDEX `products_slug_index` (`slug`)',
            $sql
        );
        $this->assertSame(1, substr_count($sql, 'ALTER TABLE'), 'one statement, one table rebuild');
    }

    #[Test]
    public function on_mysql_only_what_an_interrupted_update_left_missing_is_in_the_statement(): void
    {
        $this->assertSame(
            'ALTER TABLE `products` ADD COLUMN `specifications` JSON NULL AFTER `description`, ADD COLUMN `video_url` VARCHAR(500) NULL AFTER `warranty_months`',
            $this->migration()->mysqlStatement(['specifications', 'video_url'], false)
        );
        $this->assertSame(
            'ALTER TABLE `products` ADD INDEX `products_slug_index` (`slug`)',
            $this->migration()->mysqlStatement([], true)
        );
    }

    #[Test]
    public function it_is_reversible(): void
    {
        $this->migration()->down();

        foreach (self::COLUMNS as $column) {
            $this->assertFalse(Schema::hasColumn('products', $column), "products.{$column} should be gone");
        }
        $this->assertFalse(Schema::hasIndex('products', ['slug']));

        // ...and reversing twice is harmless, as is going forward again.
        $this->migration()->down();
        $this->migration()->up();
        $this->assertTrue(Schema::hasColumn('products', 'video_url'));
    }
}
