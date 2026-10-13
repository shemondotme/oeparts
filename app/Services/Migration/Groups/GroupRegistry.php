<?php

namespace App\Services\Migration\Groups;

/**
 * The single answer to "which group does this table belong to?". Every table of the application is
 * assigned to exactly one group (`seo_meta` to two, split by what the row describes); a table that is
 * not assigned at all is treated as system data at run time and listed as "unclassified", so a table
 * added by a future migration can never travel by accident. The guard tests (GroupRegistryTest) compare
 * this list with the real migrated schema in both directions and fail the build when they drift.
 */
final class GroupRegistry
{
    /** Table whose rows are divided between two groups. */
    public const SHARED_TABLE = 'seo_meta';

    /** @var array<string, Group> */
    private array $groups = [];

    /** @var array<string, array<int, GroupKey>> table => groups that own (part of) it */
    private array $owners = [];

    public function __construct()
    {
        foreach (self::definitions() as $group) {
            $this->groups[$group->key->value] = $group;

            foreach ($group->allTables() as $table) {
                $this->owners[$table][] = $group->key;
            }
        }
    }

    /** @return array<string, Group> in the order people see them */
    public function all(): array
    {
        return $this->groups;
    }

    public function get(GroupKey|string $key): Group
    {
        $key = $key instanceof GroupKey ? $key->value : $key;

        return $this->groups[$key] ?? throw new \InvalidArgumentException("There is no data group called '{$key}'.");
    }

    public function has(string $key): bool
    {
        return isset($this->groups[$key]);
    }

    /** @return array<string, Group> the groups a person can choose: everything except system */
    public function selectable(): array
    {
        return array_filter($this->groups, static fn (Group $g): bool => $g->isSelectable());
    }

    /** @return array<int, GroupKey> the groups that are on unless the person turns them off */
    public function defaultOn(): array
    {
        return array_values(array_map(
            static fn (Group $g): GroupKey => $g->key,
            array_filter($this->selectable(), static fn (Group $g): bool => $g->defaultOn)
        ));
    }

    /**
     * @return array<int, GroupKey> the groups that own (part of) a table; empty for an unassigned one
     */
    public function ownersOf(string $table): array
    {
        return $this->owners[$table] ?? [];
    }

    /** The one group that owns a table in full, or null for the shared table and for unassigned ones. */
    public function ownerOf(string $table): ?GroupKey
    {
        $owners = $this->ownersOf($table);

        return count($owners) === 1 && ! isset($this->get($owners[0])->slices[$table]) ? $owners[0] : null;
    }

    /** @return array<int, string> */
    public function systemTables(): array
    {
        return $this->get(GroupKey::System)->tables;
    }

    /** @return array<int, string> every table that may be exported: all assigned tables except system ones */
    public function exportableTables(): array
    {
        $tables = [];

        foreach ($this->selectable() as $group) {
            array_push($tables, ...$group->allTables());
        }

        return array_values(array_unique($tables));
    }

    /** @return array<int, string> every assigned table, system included */
    public function allTables(): array
    {
        return array_keys($this->owners);
    }

    /**
     * Tables of the live database nobody assigned. At run time they are treated as system data (not
     * exported) and shown to the operator so they get classified.
     *
     * @param  iterable<string>  $liveTables
     * @return array<int, string>
     */
    public function unclassified(iterable $liveTables): array
    {
        $unknown = [];

        foreach ($liveTables as $table) {
            if (! isset($this->owners[$table])) {
                $unknown[] = $table;
            }
        }

        sort($unknown);

        return $unknown;
    }

    /**
     * Assigned tables that do not exist in the live database.
     *
     * @param  iterable<string>  $liveTables
     * @return array<int, string>
     */
    public function phantoms(iterable $liveTables): array
    {
        $live = array_flip(is_array($liveTables) ? $liveTables : iterator_to_array($liveTables, false));
        $missing = array_values(array_filter($this->allTables(), static fn (string $t): bool => ! isset($live[$t])));
        sort($missing);

        return $missing;
    }

    /**
     * Tables (shared slices included) of a selection of groups.
     *
     * @param  array<int, GroupKey>  $keys
     * @return array<int, string>
     */
    public function tablesOf(array $keys): array
    {
        $tables = [];

        foreach ($keys as $key) {
            array_push($tables, ...$this->get($key)->allTables());
        }

        return array_values(array_unique($tables));
    }

    /**
     * File roots of a selection of groups.
     *
     * @param  array<int, GroupKey>  $keys
     * @return array<int, FileRoot>
     */
    public function fileRootsOf(array $keys): array
    {
        $roots = [];

        foreach ($keys as $key) {
            array_push($roots, ...$this->get($key)->files);
        }

        return $roots;
    }

    /** @return array<int, Group> */
    private static function definitions(): array
    {
        $roots = static fn (string ...$specs): array => array_map(FileRoot::parse(...), $specs);

        return [
            new Group(GroupKey::Core, true, [
                'settings', 'languages', 'language_strings', 'admins', 'roles', 'permissions', 'model_has_roles',
                'model_has_permissions', 'role_has_permissions', 'sequences', 'ip_blocklists',
            ], files: $roots('public:branding', 'public:og-images', 'public:logos')),

            new Group(GroupKey::Catalog, true, [
                'manufacturers', 'car_models', 'conditions', 'products', 'product_car_models', 'product_cross_references',
                'product_images', 'product_reviews', 'inventory_logs',
            ], slices: [self::SHARED_TABLE => 'product']),

            new Group(GroupKey::CatalogFiles, true, [], files: $roots('public:product-images'), requires: [GroupKey::Catalog]),

            new Group(GroupKey::Media, true, ['media_files'], files: $roots('public:media')),

            new Group(GroupKey::Content, true, [
                'pages', 'sections', 'section_versions', 'menus', 'menu_items', 'redirects', 'content_revisions',
                'blog_posts', 'blog_tags', 'blog_post_tags', 'categories', 'testimonials', 'faqs',
            ], slices: [self::SHARED_TABLE => 'other'], files: $roots('public:editor')),

            new Group(GroupKey::Customers, true, ['users', 'user_addresses', 'contact_messages', 'part_inquiries']),

            new Group(GroupKey::Sales, true, [
                'orders', 'order_items', 'order_notes', 'order_status_history', 'payments', 'refund_requests', 'coupons', 'coupon_usages',
            ]),

            new Group(GroupKey::SalesFiles, true, [], files: $roots('local:payment-proofs', 'local:refund-images'), requires: [GroupKey::Sales]),

            new Group(GroupKey::Invoicing, true, ['custom_invoices', 'custom_invoice_payments', 'invoice_clients', 'invoice_bank_accounts']),

            new Group(GroupKey::ShippingTax, true, ['shipping_zones', 'shipping_countries', 'shipping_methods', 'carriers', 'tax_rates']),

            new Group(GroupKey::Marketing, true, ['newsletter_subscribers', 'newsletter_campaigns', 'newsletter_campaign_recipients']),

            new Group(GroupKey::AdminPrefs, true, ['saved_views', 'admin_push_preferences', 'push_topics']),

            new Group(GroupKey::Carts, false, ['carts', 'cart_items', 'abandoned_carts']),

            new Group(GroupKey::Logs, false, [
                'activity_logs', 'email_logs', 'login_logs', 'search_logs', 'failed_search_logs', 'not_found_logs',
                'not_found_log_snapshots', 'indexnow_push_logs', 'cron_logs', 'bulk_update_logs', 'product_import_runs',
                'health_check_snapshots', 'cache_metric_snapshots', 'core_web_vitals_snapshots', 'notifications',
            ]),

            new Group(GroupKey::System, false, [
                'backup_runs', 'backup_parts', 'update_histories', 'cleanup_runs', 'cleanup_run_items', 'cleanup_target_stats',
                'cleanup_disk_samples', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs',
                'password_reset_tokens', 'otps', 'personal_access_tokens', 'admin_sessions', 'admin_push_subscriptions',
                'admin_push_deferred', 'admin_push_deliveries', 'migrations',
            ]),
        ];
    }
}
