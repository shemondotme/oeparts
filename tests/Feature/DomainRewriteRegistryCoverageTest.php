<?php

namespace Tests\Feature;

use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Rewrite\DomainRewriteRegistry;
use App\Services\Migration\Rewrite\RewriteKind;
use App\Services\Migration\Rewrite\RewriteTarget;
use App\Services\Migration\Rewrite\SettingAction;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 5.2: the three coverage tests that keep the address rewrite honest. A new migration that adds a column
 * which could hold an address fails the build until somebody decides what the rewrite does with it, and a new
 * setting that looks like an address fails it until it has a rule. Nothing here depends on the registry's own
 * opinion of what is important: the columns come from the live schema and the settings from the seeder.
 */
class DomainRewriteRegistryCoverageTest extends TestCase
{
    use RefreshDatabase;

    /** The names that mean "this could hold a link or a text with a link in it" (plan 9.2). */
    private const COULD_HOLD_AN_ADDRESS = '/url|link|href|canonical|body|content|html|description|note|message|text|snapshot|instructions/i';

    private const TEXTUAL = ['varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext', 'json'];

    /** @return array<int, string> tables that can be exported at all: not the system tables (sessions, jobs, caches...) */
    private function contentTables(): array
    {
        $tables = (new GroupRegistry)->exportableTables();
        sort($tables);

        return $tables;
    }

    // ---- 1. every column that could hold an address has been decided --------------------------------------

    #[Test]
    public function every_column_that_could_hold_an_address_is_rewritten_or_deliberately_left_with_a_reason(): void
    {
        $registry = new DomainRewriteRegistry;
        $undecided = [];

        foreach ($this->contentTables() as $table) {
            foreach (Schema::getColumns($table) as $column) {
                $name = (string) $column['name'];

                if (! in_array((string) $column['type_name'], self::TEXTUAL, true) || preg_match(self::COULD_HOLD_AN_ADDRESS, $name) !== 1) {
                    continue;
                }

                $target = $registry->target($table);
                $rewritten = $target !== null && in_array($name, $target->columnNames(), true);

                if (! $rewritten && $registry->classification($table, $name) === null) {
                    $undecided[] = "{$table}.{$name}";
                }
            }
        }

        $this->assertSame([], $undecided, 'These columns could hold an address and the domain rewrite has no decision about them. Add each to DomainRewriteRegistry as a target or as a classified column with a reason.');
    }

    #[Test]
    public function every_registered_column_and_classification_still_exists_and_has_a_reason(): void
    {
        $registry = new DomainRewriteRegistry;
        $stale = [];

        foreach ($registry->targets() as $table => $target) {
            if (! Schema::hasTable($table)) {
                $stale[] = "{$table} (target table)";

                continue;
            }

            foreach ($target->columnNames() as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    $stale[] = "{$table}.{$column} (target column)";
                }
            }

            foreach (array_keys($target->where) as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    $stale[] = "{$table}.{$column} (where column)";
                }
            }
        }

        foreach ($registry->classified() as $key => [$kind, $reason]) {
            [$table, $column] = explode('.', $key, 2);

            if (! Schema::hasTable($table) || ($column !== '*' && ! Schema::hasColumn($table, $column))) {
                $stale[] = "{$key} (classified)";
            }

            if ($kind->isRewritten()) {
                $stale[] = "{$key} is classified with a kind that is rewritten";
            }

            if (trim($reason) === '') {
                $stale[] = "{$key} has no reason";
            }
        }

        $this->assertSame([], $stale);
    }

    #[Test]
    public function a_column_is_never_both_rewritten_and_left_alone(): void
    {
        $registry = new DomainRewriteRegistry;
        $both = [];

        foreach ($registry->targets() as $table => $target) {
            foreach ($target->columnNames() as $column) {
                if ($registry->classification($table, $column) !== null) {
                    $both[] = "{$table}.{$column}";
                }
            }
        }

        $this->assertSame([], $both);
    }

    #[Test]
    public function a_target_can_only_be_built_from_kinds_that_are_rewritten(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RewriteTarget('pages', ['content' => RewriteKind::Preserve]);
    }

    // ---- 2. every target can be walked ------------------------------------------------------------------------

    #[Test]
    public function every_target_has_a_single_integer_id_primary_key_and_textual_columns(): void
    {
        $problems = [];

        foreach ((new DomainRewriteRegistry)->targets() as $table => $target) {
            $primary = collect(Schema::getIndexes($table))->firstWhere('primary', true);

            if (($primary['columns'] ?? null) !== [$target->primaryKey]) {
                $problems[] = "{$table}: the primary key is ".json_encode($primary['columns'] ?? null).", not [{$target->primaryKey}]";
            }

            $idColumn = collect(Schema::getColumns($table))->firstWhere('name', $target->primaryKey);
            if ($idColumn === null || ! in_array((string) $idColumn['type_name'], ['bigint', 'int', 'integer', 'mediumint', 'smallint'], true)) {
                $problems[] = "{$table}.{$target->primaryKey} is not an integer";
            }

            foreach ($target->columnNames() as $column) {
                $info = collect(Schema::getColumns($table))->firstWhere('name', $column);

                if ($info === null || ! in_array((string) $info['type_name'], self::TEXTUAL, true)) {
                    $problems[] = "{$table}.{$column} is not a text column";
                }
            }
        }

        $this->assertSame([], $problems);
    }

    #[Test]
    public function every_target_table_belongs_to_a_group_that_a_package_can_carry_and_the_big_one_is_the_bulk_tier(): void
    {
        $registry = new GroupRegistry;
        $targets = (new DomainRewriteRegistry)->targets();

        foreach (array_keys($targets) as $table) {
            $this->assertNotEmpty($registry->ownersOf($table), "{$table} is in no group");
            $this->assertNotContains(GroupKey::System, $registry->ownersOf($table), "{$table} is a system table");
        }

        $this->assertSame(RewriteTarget::BULK, $targets['products']->tier, 'products has a million rows in production');
        $bulk = array_keys(array_filter($targets, fn (RewriteTarget $t): bool => $t->tier === RewriteTarget::BULK));
        $this->assertSame(['products'], $bulk, 'every other target is small enough for one budgeted pass');
    }

    // ---- 3. every setting that looks like an address has a rule -------------------------------------------------

    #[Test]
    public function every_seeded_setting_that_looks_like_an_address_or_a_mailbox_has_a_rule(): void
    {
        $registry = new DomainRewriteRegistry;
        $unruled = [];

        foreach (SettingsSeeder::definitions() as $row) {
            if (! empty($row['encrypted'])) {
                continue;
            }

            $looksLikeOne = preg_match('/url|host|email|mail|link|domain|canonical|(^|_)css($|_)|(^|_)scripts?($|_)/i', $row['key']) === 1
                || preg_match('~https?://|//[a-z0-9.-]+\.[a-z]{2,}|[\w.+-]+@[\w-]+\.[\w.-]+~i', (string) $row['value']) === 1;

            if ($looksLikeOne && $registry->settingRule($row['group'], $row['key']) === null) {
                $unruled[] = "{$row['group']}.{$row['key']}";
            }
        }

        $this->assertSame([], $unruled, 'These settings look like an address, a host or a mailbox and have no rule in DomainRewriteRegistry::settingRules().');
    }

    #[Test]
    public function the_setting_rules_name_real_groups_and_the_site_address_and_canonical_host_are_special(): void
    {
        $registry = new DomainRewriteRegistry;
        $groups = array_unique(array_column(SettingsSeeder::definitions(), 'group'));
        $unknown = [];

        foreach (array_keys($registry->settingRules()) as $pattern) {
            if (! in_array(explode('.', $pattern, 2)[0], $groups, true) && $pattern !== 'appearance.custom_css' && $pattern !== 'announcement.url') {
                $unknown[] = $pattern;
            }
        }

        $this->assertSame([], $unknown);
        $this->assertSame(SettingAction::Set, $registry->settingRule('general', 'site_url')[0]);
        $this->assertSame(SettingAction::CanonicalHost, $registry->settingRule('seo', 'canonical_host')[0]);
        $this->assertSame(SettingAction::Clear, $registry->settingRule('seo', 'gsc_property_url')[0]);
        $this->assertSame(SettingAction::Url, $registry->settingRule('social_links', 'facebook_url')[0], 'a family rule matches every member');
        $this->assertNull($registry->settingRule('tax', 'default_vat_rate'), 'an ordinary setting has no special rule: it is processed by its type');
    }

    #[Test]
    public function only_the_actions_that_change_a_value_say_so(): void
    {
        $changing = array_filter(SettingAction::cases(), fn (SettingAction $a): bool => $a->changesValue());

        $this->assertEqualsCanonicalizing([SettingAction::Set, SettingAction::Clear, SettingAction::Url, SettingAction::Css], $changing);
    }
}
