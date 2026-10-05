<?php

namespace Tests\Feature;

use App\Services\Cleanup\SchemaAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Report-only DB audit for the admin Cleanup dashboard. The headline test runs
 * this against THIS APP'S REAL migrations (RefreshDatabase actually migrates the
 * test DB) — the only trustworthy proof the static source scanner agrees with
 * what `php artisan migrate` really produces, and a regression guard: two real
 * migrations already broke a naive version of this scanner (see the focused
 * fixture tests below) before this ever shipped, so any future migration using
 * an unusual pattern fails THIS test rather than silently producing a false
 * "unexpected table" on an operator's cleanup dashboard.
 */
class SchemaAuditorTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtures;

    protected function tearDown(): void
    {
        if (isset($this->fixtures)) {
            array_map('unlink', glob($this->fixtures.'/*.php') ?: []);
            @rmdir($this->fixtures);
        }
        parent::tearDown();
    }

    /** @param array<string,string> $files filename => PHP source */
    private function auditorWithFixtures(array $files): SchemaAuditor
    {
        $this->fixtures = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-schema-audit-'.getmypid();
        @mkdir($this->fixtures, 0775, true);
        foreach ($files as $name => $source) {
            file_put_contents($this->fixtures.'/'.$name, $source);
        }

        return new SchemaAuditor($this->fixtures);
    }

    #[Test]
    public function a_freshly_migrated_real_database_has_no_findings_at_all(): void
    {
        // RefreshDatabase has already run every real migration in database/migrations/
        // against the sqlite test connection by the time this test body runs.
        $report = app(SchemaAuditor::class)->audit();

        $this->assertSame([], $report['expected_missing']);
        $this->assertSame([], $report['unexpected']);
    }

    #[Test]
    public function a_table_created_outside_any_migration_is_flagged_as_unexpected(): void
    {
        DB::statement('CREATE TABLE a_manually_created_table (id integer)');

        $report = app(SchemaAuditor::class)->audit();

        $this->assertContains('a_manually_created_table', $report['unexpected']);
    }

    #[Test]
    public function a_table_a_migration_created_but_that_is_now_missing_is_flagged(): void
    {
        Schema::drop('settings');

        $report = app(SchemaAuditor::class)->audit();

        $this->assertContains('settings', $report['expected_missing']);
    }

    #[Test]
    public function it_does_not_confuse_safe_schemas_context_label_with_a_table_name(): void
    {
        // SafeSchema::create('context label', 'table_name', $callback) — the FIRST
        // argument is a human-readable label for its own error log, not a table.
        // A naive `Schema::create('` scan matches the substring inside
        // `SafeSchema::create(` too and would have captured "my_context_label"
        // as a phantom expected table.
        $auditor = $this->auditorWithFixtures([
            '2026_01_01_000000_create_via_safe_schema.php' => <<<'PHP'
<?php
use App\Support\Database\SafeSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
return new class extends Migration {
    public function up(): void {
        SafeSchema::create('my_context_label', 'real_table_name', function (Blueprint $table) {
            $table->id();
        });
    }
    public function down(): void {}
};
PHP,
        ]);

        $expected = $auditor->expectedTables();

        $this->assertContains('real_table_name', $expected);
        $this->assertNotContains('my_context_label', $expected);
    }

    #[Test]
    public function a_drop_then_recreate_within_one_migrations_up_nets_to_still_expected(): void
    {
        // A real shape in this codebase: a per-driver branch where the sqlite path
        // (which can't ALTER a column's enum constraint) drops and recreates the
        // SAME table within one up() — unlike two separate migrations, the drop
        // comes BEFORE the create in the source. Applying "every create, then
        // every drop" per file (instead of in actual source order) silently
        // undid this migration's own net effect and wrongly flagged a table that
        // plainly still exists as unexpected.
        $auditor = $this->auditorWithFixtures([
            '2026_01_01_000000_drop_then_recreate.php' => <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::drop('widgets');
        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
        });
    }
    public function down(): void {}
};
PHP,
        ]);

        $this->assertContains('widgets', $auditor->expectedTables());
    }

    #[Test]
    public function a_rename_moves_the_expectation_to_the_new_name(): void
    {
        // The actual shape of a real migration here: create a _tmp table with a
        // changed constraint, copy data over, drop the original, rename the tmp
        // table into its place. Only the FINAL name should end up expected.
        $auditor = $this->auditorWithFixtures([
            '2026_01_01_000000_rename_swap.php' => <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('widgets_tmp', function (Blueprint $table) {
            $table->id();
        });
        Schema::drop('widgets');
        Schema::rename('widgets_tmp', 'widgets');
    }
    public function down(): void {}
};
PHP,
        ]);

        $expected = $auditor->expectedTables();

        $this->assertContains('widgets', $expected);
        $this->assertNotContains('widgets_tmp', $expected);
    }

    #[Test]
    public function a_migrations_own_down_method_is_never_consulted(): void
    {
        // down() dropping what up() just created is the rollback path, not a
        // schema change — it must never affect what we expect to currently exist.
        $auditor = $this->auditorWithFixtures([
            '2026_01_01_000000_create_widgets.php' => <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
        });
    }
    public function down(): void {
        Schema::dropIfExists('widgets');
    }
};
PHP,
        ]);

        $this->assertContains('widgets', $auditor->expectedTables());
    }

    #[Test]
    public function a_forward_schema_shrinking_migration_removes_the_expectation(): void
    {
        // A genuine later migration dropping an earlier one's table for good
        // (this codebase's own drop_admin_dashboards_table is exactly this
        // shape) — unlike the down()-only case above, this drop is in up().
        $auditor = $this->auditorWithFixtures([
            '2026_01_01_000000_create_widgets.php' => <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
        });
    }
    public function down(): void {}
};
PHP,
            '2026_02_01_000000_drop_widgets.php' => <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::dropIfExists('widgets');
    }
    public function down(): void {}
};
PHP,
        ]);

        $this->assertNotContains('widgets', $auditor->expectedTables());
    }
}
