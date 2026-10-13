<?php

namespace Tests\Unit\Migration;

use App\Services\Migration\Export\DependencyResolver;
use App\Services\Migration\Export\FkEdge;
use App\Services\Migration\Export\FkGraph;
use App\Services\Migration\Export\Reason;
use App\Services\Migration\Export\Resolution;
use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Groups\Preset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 3.3: which groups must travel together, worked out from the foreign keys of the real database.
 */
class DependencyResolverTest extends TestCase
{
    use RefreshDatabase;

    private GroupRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new GroupRegistry;
    }

    private function resolver(?FkGraph $graph = null): DependencyResolver
    {
        return new DependencyResolver($this->registry, $graph ?? new FkGraph);
    }

    /** @return array<int, string> */
    private function values(array $keys): array
    {
        return array_map(fn (GroupKey $k): string => $k->value, $keys);
    }

    private function edge(string $child, string $column, string $parent, bool $strong): FkEdge
    {
        return new FkEdge($child, [$column], $parent, ['id'], $strong);
    }

    // ---- the real graph --------------------------------------------------------------------

    #[Test]
    public function the_graph_read_from_the_database_equals_the_committed_snapshot(): void
    {
        $fixture = json_decode((string) file_get_contents(base_path('tests/Fixtures/migration/fk-edges.json')), true, 8, JSON_THROW_ON_ERROR);

        $this->assertSame($fixture['edges'], (new FkGraph)->signatures(), 'a foreign key was added, removed or changed: classify it, then regenerate tests/Fixtures/migration/fk-edges.json');
    }

    #[Test]
    public function a_table_referencing_itself_is_not_an_edge(): void
    {
        $edges = (new FkGraph)->signatures();

        $this->assertSame([], array_values(array_filter($edges, fn (string $s): bool => str_starts_with($s, 'categories.parent_id->categories'))));
    }

    #[Test]
    public function no_exportable_table_has_a_reference_the_registry_cannot_place(): void
    {
        foreach ((new FkGraph)->edges() as $edge) {
            if ($this->registry->ownerOf($edge->child) === null) {
                continue; // the shared seo table
            }

            $this->assertNotNull($this->registry->ownerOf($edge->parent), "{$edge->signature()} points at a table without a single owner");

            if ($this->registry->ownerOf($edge->child) !== GroupKey::System) {
                $this->assertNotSame(GroupKey::System, $this->registry->ownerOf($edge->parent), "{$edge->signature()}: a shop table must not depend on system data");
            }
        }
    }

    // ---- what a selection pulls in ---------------------------------------------------------

    #[Test]
    public function carts_pull_the_catalog_in_and_say_why(): void
    {
        $resolution = $this->resolver()->resolve([GroupKey::Carts]);

        $this->assertTrue($resolution->wasAdded(GroupKey::Catalog));
        $this->assertSame(Reason::FOREIGN_KEY, $resolution->added['catalog'][0]->type);
        $this->assertSame('carts needs catalog: cart_items.product_id -> products.id is NOT NULL', $resolution->added['catalog'][0]->technical());
        $this->assertFalse($resolution->wasAdded(GroupKey::Customers), 'carts.user_id is optional');
    }

    #[Test]
    public function orders_alone_keep_customers_and_products_out_and_cut_the_optional_references(): void
    {
        $resolution = $this->resolver()->resolve([GroupKey::Sales]);

        $this->assertSame(['core', 'sales'], $this->values($resolution->groups));
        $this->assertSame([], $resolution->added);

        $cut = $resolution->nullifiedColumns();
        $this->assertSame(['carrier_id', 'shipping_method_id', 'user_id'], $cut['orders']);
        $this->assertSame(['product_id'], $cut['order_items']);
        $this->assertArrayHasKey('coupons', $cut);
    }

    #[Test]
    public function a_file_group_brings_the_group_whose_rows_point_at_its_files(): void
    {
        $products = $this->resolver()->resolve([GroupKey::CatalogFiles]);
        $this->assertSame(['core', 'catalog', 'catalog_files'], $this->values($products->groups));
        $this->assertSame(Reason::REQUIRES, $products->added['catalog'][0]->type);

        $proofs = $this->resolver()->resolve([GroupKey::SalesFiles]);
        $this->assertSame(['core', 'sales', 'sales_files'], $this->values($proofs->groups));
    }

    #[Test]
    public function core_is_always_there_and_system_cannot_be_asked_for(): void
    {
        $this->assertSame(['core'], $this->values($this->resolver()->resolve([])->groups));
        $this->assertSame(['core', 'media'], $this->values($this->resolver()->resolve([GroupKey::Media])->groups));

        $this->expectException(\InvalidArgumentException::class);
        $this->resolver()->resolve([GroupKey::System]);
    }

    #[Test]
    public function a_selection_that_names_a_group_twice_or_in_any_order_resolves_the_same(): void
    {
        $a = $this->resolver()->resolve([GroupKey::Sales, GroupKey::Catalog, GroupKey::Sales]);
        $b = $this->resolver()->resolve([GroupKey::Catalog, GroupKey::Sales]);

        $this->assertSame($a->toArray(), $b->toArray());
    }

    // ---- invariants over everything a person could tick -------------------------------------

    /** @return array<int, string> every violation of the resolution rules, empty when it is sound */
    private function violations(Resolution $resolution, FkGraph $graph): array
    {
        $problems = [];
        $exported = array_flip($resolution->tables);

        foreach ($resolution->tables as $table) {
            if (in_array($table, $this->registry->systemTables(), true)) {
                $problems[] = "{$table} is system data";
            }
        }

        foreach ($graph->edges() as $edge) {
            if (! isset($exported[$edge->child])) {
                continue;
            }

            if ($edge->strong && ! isset($exported[$edge->parent])) {
                $problems[] = "strong parent missing: {$edge->signature()}";
            }

            if (! $edge->strong && ! isset($exported[$edge->parent]) && ! in_array($edge, $resolution->nullified, true)) {
                $problems[] = "dangling optional reference not cut: {$edge->signature()}";
            }
        }

        $position = array_flip($resolution->importOrder());
        foreach ($graph->edges() as $edge) {
            if (isset($exported[$edge->child], $exported[$edge->parent]) && ! in_array($edge, $resolution->brokenCycles, true)
                && $position[$edge->parent] > $position[$edge->child]) {
                $problems[] = "parent loaded after its child: {$edge->signature()}";
            }
        }

        return $problems;
    }

    #[Test]
    public function every_preset_resolves_to_a_selection_with_no_missing_parent_and_a_loadable_order(): void
    {
        $graph = new FkGraph;

        foreach (Preset::cases() as $preset) {
            $resolution = $this->resolver($graph)->resolve($preset->groups());

            $this->assertSame([], $this->violations($resolution, $graph), $preset->value);
            $this->assertSame([], $resolution->added, "{$preset->value} was designed to be closed under strong references");
        }
    }

    #[Test]
    public function three_hundred_random_selections_all_resolve_soundly(): void
    {
        $graph = new FkGraph;
        $selectable = array_values(array_filter(GroupKey::cases(), fn (GroupKey $k): bool => $k !== GroupKey::System));
        mt_srand(20261013);

        for ($i = 0; $i < 300; $i++) {
            $selection = array_values(array_filter($selectable, fn (): bool => mt_rand(0, 1) === 1));
            $resolution = $this->resolver($graph)->resolve($selection);

            $this->assertSame([], $this->violations($resolution, $graph), 'selection: '.implode(',', $this->values($selection)));
            $this->assertSame(count($resolution->tables), count(array_unique($resolution->tables)), 'a table listed twice');
        }
    }

    #[Test]
    public function the_order_is_children_first_and_reversible_into_parents_first(): void
    {
        $resolution = $this->resolver()->resolve(Preset::FullLive->groups());

        $loadAt = array_flip($resolution->importOrder());
        $readAt = array_flip($resolution->tables);

        $this->assertLessThan($loadAt['order_items'], $loadAt['products'], 'a target loads products before the order lines that point at them');
        $this->assertLessThan($readAt['products'], $readAt['order_items'], 'an export reads the order lines before the products');
        $this->assertSame(array_reverse($resolution->tables), $resolution->importOrder());
    }

    #[Test]
    public function the_same_selection_always_gives_the_same_resolution(): void
    {
        $first = $this->resolver()->resolve(Preset::Staging->groups());
        $second = $this->resolver(new FkGraph)->resolve(Preset::Staging->groups());

        $this->assertSame($first->toArray(), $second->toArray());
    }

    // ---- references the real schema does not have (planted) ----------------------------------

    #[Test]
    public function a_new_not_null_reference_is_picked_up_without_changing_any_code(): void
    {
        $planted = FkGraph::fromEdges([$this->edge('pages', 'product_id', 'products', true)]);

        $resolution = $this->resolver($planted)->resolve([GroupKey::Content]);

        $this->assertSame(['core', 'catalog', 'content'], $this->values($resolution->groups));
        $this->assertSame('content needs catalog: pages.product_id -> products.id is NOT NULL', $resolution->added['catalog'][0]->technical());
    }

    #[Test]
    public function the_same_reference_made_optional_pulls_nothing_in_and_is_cut(): void
    {
        $planted = FkGraph::fromEdges([$this->edge('pages', 'product_id', 'products', false)]);

        $resolution = $this->resolver($planted)->resolve([GroupKey::Content]);

        $this->assertSame(['core', 'content'], $this->values($resolution->groups));
        $this->assertSame(['pages' => ['product_id']], $resolution->nullifiedColumns());
    }

    #[Test]
    public function a_chain_of_not_null_references_is_followed_to_the_end(): void
    {
        // marketing -> sales -> catalog, each NOT NULL.
        $planted = FkGraph::fromEdges([
            $this->edge('newsletter_campaigns', 'order_id', 'orders', true),
            $this->edge('order_items', 'product_id', 'products', true),
        ]);

        $resolution = $this->resolver($planted)->resolve([GroupKey::Marketing]);

        $this->assertSame(['core', 'catalog', 'sales', 'marketing'], $this->values($resolution->groups));
    }

    #[Test]
    public function a_cycle_of_optional_references_is_ordered_by_ignoring_them_and_reported(): void
    {
        $planted = FkGraph::fromEdges([
            $this->edge('products', 'best_review_id', 'product_reviews', false),
            $this->edge('product_reviews', 'product_id', 'products', true),
        ]);

        $resolution = $this->resolver($planted)->resolve([GroupKey::Catalog]);

        $this->assertCount(1, $resolution->brokenCycles);
        $this->assertSame('products.best_review_id->product_reviews.id:weak', $resolution->brokenCycles[0]->signature());
        $this->assertLessThan(array_search('product_reviews', $resolution->importOrder(), true), array_search('products', $resolution->importOrder(), true));
    }

    #[Test]
    public function a_cycle_of_not_null_references_cannot_be_loaded_and_is_an_error(): void
    {
        $planted = FkGraph::fromEdges([
            $this->edge('products', 'a_id', 'product_reviews', true),
            $this->edge('product_reviews', 'b_id', 'products', true),
        ]);

        $this->expectException(\LogicException::class);
        $this->resolver($planted)->resolve([GroupKey::Catalog]);
    }

    #[Test]
    public function a_shop_table_that_needs_system_data_or_an_unassigned_table_is_an_error_not_a_guess(): void
    {
        foreach (['jobs', 'zz_brand_new_table'] as $parent) {
            try {
                $this->resolver(FkGraph::fromEdges([$this->edge('pages', 'x_id', $parent, true)]))->resolve([GroupKey::Content]);
                $this->fail("a strong reference to {$parent} was accepted");
            } catch (\LogicException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function an_optional_reference_to_system_or_unknown_data_is_simply_cut(): void
    {
        $resolution = $this->resolver(FkGraph::fromEdges([$this->edge('pages', 'x_id', 'jobs', false)]))->resolve([GroupKey::Content]);

        $this->assertSame(['pages' => ['x_id']], $resolution->nullifiedColumns());
    }

    #[Test]
    public function the_edge_texts_are_the_same_whatever_the_database(): void
    {
        $edge = new FkEdge('order_items', ['product_id'], 'products', ['id'], false);

        $this->assertSame('order_items.product_id->products.id:weak', $edge->signature());
        $this->assertSame('order_items.product_id -> products.id is optional', $edge->describe());
    }
}
