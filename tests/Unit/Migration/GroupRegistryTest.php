<?php

namespace Tests\Unit\Migration;

use App\Services\Migration\Groups\FileRoot;
use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Groups\Preset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AssertsGroupRegistryAgainstSchema;
use Tests\TestCase;

/**
 * Phase 3.2: the fifteen data groups and four presets, and the guard that keeps them in step with the
 * database. The schema guards run on the real migrated SQLite schema here and again on MySQL
 * (tests/Mysql/GroupRegistrySchemaTest).
 */
class GroupRegistryTest extends TestCase
{
    use AssertsGroupRegistryAgainstSchema;
    use RefreshDatabase;

    // ---- the guard against the real schema (4.4) ------------------------------------------

    #[Test]
    public function the_registry_and_the_real_schema_list_exactly_the_same_tables(): void
    {
        $this->assertRegistryMatchesTheRealSchema();
    }

    #[Test]
    public function the_migration_scan_agrees_with_the_registry_as_a_second_opinion(): void
    {
        $this->assertSchemaAuditorAgreesWithTheRegistry();
    }

    #[Test]
    public function every_table_belongs_to_exactly_one_group_except_the_shared_seo_table(): void
    {
        $this->assertSharedTableIsPartitionedExactly();
    }

    #[Test]
    public function every_exportable_table_has_a_key_to_page_by(): void
    {
        $this->assertEveryExportableTableCanBePagedByKey();
    }

    // ---- the registry itself ----------------------------------------------------------------

    #[Test]
    public function there_are_fifteen_groups_in_the_order_people_see_them(): void
    {
        $registry = new GroupRegistry;

        $this->assertSame(
            ['core', 'catalog', 'catalog_files', 'media', 'content', 'customers', 'sales', 'sales_files', 'invoicing', 'shipping_tax', 'marketing', 'admin_prefs', 'carts', 'logs', 'system'],
            array_keys($registry->all())
        );
        $this->assertCount(15, GroupKey::cases());
    }

    #[Test]
    public function core_is_mandatory_and_system_can_never_be_chosen(): void
    {
        $registry = new GroupRegistry;

        $this->assertTrue($registry->get(GroupKey::Core)->isMandatory());
        $this->assertFalse($registry->get(GroupKey::Catalog)->isMandatory());
        $this->assertArrayNotHasKey('system', $registry->selectable());
        $this->assertArrayHasKey('core', $registry->selectable());
        $this->assertNotContains('system', array_map(fn (GroupKey $k): string => $k->value, $registry->defaultOn()));
        $this->assertNotContains('sessions', $registry->exportableTables());
        $this->assertContains('users', $registry->exportableTables());
    }

    #[Test]
    public function carts_and_logs_are_off_by_default_and_everything_else_is_on(): void
    {
        $registry = new GroupRegistry;
        $off = array_map(fn ($g) => $g->key->value, array_filter($registry->selectable(), fn ($g) => ! $g->defaultOn));

        $this->assertEqualsCanonicalizing(['carts', 'logs'], array_values($off));
    }

    #[Test]
    public function file_groups_require_the_group_whose_rows_point_at_their_files(): void
    {
        $registry = new GroupRegistry;

        $this->assertSame([GroupKey::Catalog], $registry->get(GroupKey::CatalogFiles)->requires);
        $this->assertSame([GroupKey::Sales], $registry->get(GroupKey::SalesFiles)->requires);
        $this->assertSame([], $registry->get(GroupKey::CatalogFiles)->tables, 'a file group has no tables of its own');
    }

    #[Test]
    public function the_file_roots_are_the_ones_the_plan_names(): void
    {
        $registry = new GroupRegistry;
        $roots = fn (array $keys): array => array_map('strval', $registry->fileRootsOf($keys));

        $this->assertSame(['public:branding', 'public:og-images', 'public:logos'], $roots([GroupKey::Core]));
        $this->assertSame(['public:product-images'], $roots([GroupKey::CatalogFiles]));
        $this->assertSame(['public:media'], $roots([GroupKey::Media]));
        $this->assertSame(['public:editor'], $roots([GroupKey::Content]));
        $this->assertSame(['local:payment-proofs', 'local:refund-images'], $roots([GroupKey::SalesFiles]));
    }

    #[Test]
    public function a_file_root_must_be_a_plain_folder_on_a_known_disk(): void
    {
        foreach (['public:../etc', 'public:a/b', 's3:media', 'public:', ':media', 'public:Media', 'media'] as $bad) {
            try {
                FileRoot::parse($bad);
                $this->fail("accepted '{$bad}'");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function an_unassigned_table_is_reported_and_a_phantom_is_reported(): void
    {
        $registry = new GroupRegistry;

        $this->assertSame(['future_table', 'zz_other'], $registry->unclassified(['users', 'future_table', 'zz_other', 'jobs']));
        $this->assertSame(['users'], $registry->phantoms(array_values(array_diff($registry->allTables(), ['users']))));
        $this->assertSame([], $registry->phantoms($registry->allTables()));
    }

    #[Test]
    public function an_unknown_group_name_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new GroupRegistry)->get('nonsense');
    }

    #[Test]
    public function the_tables_of_a_selection_include_the_shared_slice_once(): void
    {
        $registry = new GroupRegistry;

        $tables = $registry->tablesOf([GroupKey::Catalog, GroupKey::Content]);

        $this->assertSame(1, count(array_keys($tables, 'seo_meta', true)));
        $this->assertContains('products', $tables);
        $this->assertContains('pages', $tables);
        $this->assertNotContains('users', $tables);
    }

    // ---- presets ------------------------------------------------------------------------------

    #[Test]
    public function full_live_is_everything_except_logs_carts_and_system(): void
    {
        $groups = array_map(fn (GroupKey $k): string => $k->value, Preset::FullLive->groups());

        $this->assertNotContains('logs', $groups);
        $this->assertNotContains('carts', $groups);
        $this->assertNotContains('system', $groups);
        $this->assertCount(12, $groups);
    }

    #[Test]
    public function staging_is_full_live_without_payment_proofs_and_invoices_and_scrambles_data(): void
    {
        $full = Preset::FullLive->groups();
        $staging = Preset::Staging->groups();

        $removed = array_map(
            fn (GroupKey $k): string => $k->value,
            array_values(array_filter($full, fn (GroupKey $k): bool => ! in_array($k, $staging, true)))
        );
        $this->assertEqualsCanonicalizing(['sales_files', 'invoicing'], $removed);
        $this->assertTrue(Preset::Staging->anonymises());
        $this->assertTrue(Preset::Staging->forcesSecretsOut());
        $this->assertFalse(Preset::FullLive->anonymises());
    }

    #[Test]
    public function the_two_new_store_presets_restart_numbering_and_the_others_keep_it(): void
    {
        $this->assertTrue(Preset::NewStoreSameCatalog->resetsSequences());
        $this->assertTrue(Preset::CatalogOnly->resetsSequences());
        $this->assertFalse(Preset::FullLive->resetsSequences());
        $this->assertFalse(Preset::Staging->resetsSequences());
    }

    #[Test]
    public function every_preset_includes_core_and_never_system(): void
    {
        foreach (Preset::cases() as $preset) {
            $this->assertContains(GroupKey::Core, $preset->groups(), $preset->value);
            $this->assertNotContains(GroupKey::System, $preset->groups(), $preset->value);
        }
    }

    // ---- words --------------------------------------------------------------------------------

    #[Test]
    public function every_group_and_preset_has_plain_english_text(): void
    {
        $catalogue = require lang_path('en/migration.php');

        foreach (GroupKey::cases() as $key) {
            $this->assertNotSame('', trim($catalogue['group'][$key->value]['label'] ?? ''), $key->value.' label');
            $this->assertNotSame('', trim($catalogue['group'][$key->value]['description'] ?? ''), $key->value.' description');
            $this->assertSame('migration.group.'.$key->value.'.label', $key->labelKey());
        }

        foreach (Preset::cases() as $preset) {
            $this->assertNotSame('', trim($catalogue['preset'][$preset->value]['label'] ?? ''), $preset->value.' label');
            $this->assertNotSame('', trim($catalogue['preset'][$preset->value]['description'] ?? ''), $preset->value.' description');
        }

        $this->assertSame(array_map(fn (GroupKey $k) => $k->value, GroupKey::cases()), array_keys($catalogue['group']), 'no orphan or missing group text');
    }
}
