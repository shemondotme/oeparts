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
