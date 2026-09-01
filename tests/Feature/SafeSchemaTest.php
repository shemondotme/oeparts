<?php

namespace Tests\Feature;

use App\Support\Database\SafeSchema;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SafeSchema is the fix for a real production incident: a live update
 * (v1.0.16 -> v1.0.18, 2026-09-01) failed with "Duplicate column name
 * 'slug'" because the `products` table already had the column but the
 * `migrations` tracking table didn't know it (see add_slug_to_products_table
 * migration). These MySQL error codes (1050/1060/1061/3822) don't exist on
 * SQLite — the test DB (see phpunit.xml / .env.testing) — so every test
 * here is a no-op skip off of MySQL, exactly like the ngram-index migration
 * this same class of desync already hit once before.
 */
class SafeSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function table_recovers_from_a_duplicate_column_error(): void
    {
        $this->skipUnlessMysql();

        Schema::create('safe_schema_probe', fn (Blueprint $t) => $t->id());
        Schema::table('safe_schema_probe', fn (Blueprint $t) => $t->string('already_there')->nullable());

        // The exact desync shape: the column is already live, but the
        // caller (unaware — its own hasColumn() pre-check having somehow
        // disagreed with reality) tries to add it again anyway.
        SafeSchema::table('probe', 'safe_schema_probe', function (Blueprint $table) {
            $table->string('already_there')->nullable();
        });

        $this->assertTrue(Schema::hasColumn('safe_schema_probe', 'already_there'));
    }

    #[Test]
    public function create_recovers_from_a_table_already_exists_error(): void
    {
        $this->skipUnlessMysql();

        Schema::create('safe_schema_probe_2', fn (Blueprint $t) => $t->id());

        SafeSchema::create('probe', 'safe_schema_probe_2', function (Blueprint $table) {
            $table->id();
        });

        $this->assertTrue(Schema::hasTable('safe_schema_probe_2'));
    }

    #[Test]
    public function table_rethrows_unrelated_query_exceptions(): void
    {
        $this->skipUnlessMysql();

        $this->expectException(QueryException::class);

        SafeSchema::table('probe', 'a_table_that_does_not_exist', function (Blueprint $table) {
            $table->string('x');
        });
    }

    private function skipUnlessMysql(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('SafeSchema targets MySQL-specific duplicate-object error codes.');
        }
    }
}
