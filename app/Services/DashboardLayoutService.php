<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminDashboard;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Named dashboard canvases: creation, switching, and gridstack layout
 * persistence. Layout entries are {id, x, y, w, h} in a 12-column grid.
 *
 * Legacy `admins.dashboard_preferences` (hidden/sort per widget) is migrated
 * once into the admin's first default dashboard and left untouched afterwards.
 */
class DashboardLayoutService
{
    public const GRID_COLUMNS = 12;

    public const MAX_CELL_SIZE = 12;

    /**
     * Pixel-perfect blueprint layouts per tab slug. When a slug matches,
     * this exact x/y/w/h grid is used INSTEAD OF WidgetPreferenceService::
     * WIDGET_TABS + auto packLayout() — see ensureDefaultDashboard() below.
     * Widgets not accessible to the current admin role are filtered out.
     *
     * IMPORTANT: a widget id listed here for a slug must also be listed
     * under the matching tab name in WidgetPreferenceService::WIDGET_TABS,
     * and nowhere else — the blueprint silently wins over WIDGET_TABS for
     * any slug present here, so a mismatch makes a widget render in two
     * tabs (or never reseed correctly) for newly-seeded admins. See
     * CLAUDE.md's dashboard dual-source-of-truth rule.
     */
    public const TAB_BLUEPRINT_LAYOUTS = [
        // TAB 1: COMMAND CENTER — Executive View (8 widgets)
        // Row 0 — Welcome Header  (w:12, h:2)
        // Row 1 — Health Strip    (w:12, h:1)
        // Row 2 — Order stats overview + Parts inquiry (w:9+3, h:2)
        // Row 3 — Revenue + Volume charts (w:8+4, h:5)
        // Row 4 — Order status distribution + Latest customers (w:6+6, h:5/5)
        'command-center' => [
            ['id' => 'dashboard_header',          'x' => 0, 'y' => 0,  'w' => 12, 'h' => 2],
            ['id' => 'health_strip',              'x' => 0, 'y' => 2,  'w' => 12, 'h' => 1],
            ['id' => 'order_stats_overview',      'x' => 0, 'y' => 3,  'w' => 9,  'h' => 2],
            ['id' => 'parts_inquiry',             'x' => 9, 'y' => 3,  'w' => 3,  'h' => 2],
            ['id' => 'revenue_chart',             'x' => 0, 'y' => 5,  'w' => 8,  'h' => 5],
            ['id' => 'order_volume_chart',        'x' => 8, 'y' => 5,  'w' => 4,  'h' => 5],
            ['id' => 'order_status_distribution', 'x' => 0, 'y' => 10, 'w' => 6,  'h' => 5],
            ['id' => 'latest_customers',          'x' => 6, 'y' => 10, 'w' => 6,  'h' => 5],
        ],

        // TAB 2: OPERATIONS — Action queue (6 widgets)
        // Row 0 — Recent orders, full width
        // Row 1 — Abandoned carts + Awaiting confirmation
        // Row 2 — Refunds pending + New messages
        // Row 3 — Newsletter growth, full width
        'operations' => [
            ['id' => 'recent_orders',         'x' => 0, 'y' => 0,  'w' => 12, 'h' => 4],
            ['id' => 'abandoned_carts',       'x' => 0, 'y' => 4,  'w' => 6,  'h' => 4],
            ['id' => 'awaiting_confirmation', 'x' => 6, 'y' => 4,  'w' => 6,  'h' => 4],
            ['id' => 'refunds_pending',       'x' => 0, 'y' => 8,  'w' => 6,  'h' => 4],
            ['id' => 'new_messages',          'x' => 6, 'y' => 8,  'w' => 6,  'h' => 4],
            ['id' => 'newsletter_growth',     'x' => 0, 'y' => 12, 'w' => 12, 'h' => 4],
        ],

        // TAB 3: INVENTORY & SOURCING — Analytics grid (7 widgets)
        // Row 0 — Manufacturer revenue + Stock alerts
        // Row 1 — Top searches + Failed searches
        // Row 2 — New products + Supplier scorecard
        // Row 3 — Manufacturing stats, full width
        'inventory-sourcing' => [
            ['id' => 'manufacturer_revenue', 'x' => 0, 'y' => 0,  'w' => 8,  'h' => 4],
            ['id' => 'stock_alert',          'x' => 8, 'y' => 0,  'w' => 4,  'h' => 4],
            ['id' => 'top_searches',         'x' => 0, 'y' => 4,  'w' => 6,  'h' => 4],
            ['id' => 'failed_searches',      'x' => 6, 'y' => 4,  'w' => 6,  'h' => 4],
            ['id' => 'new_products_added',   'x' => 0, 'y' => 8,  'w' => 6,  'h' => 4],
            ['id' => 'supplier_scorecard',   'x' => 6, 'y' => 8,  'w' => 6,  'h' => 4],
            ['id' => 'manufacturing_stats',  'x' => 0, 'y' => 12, 'w' => 12, 'h' => 2],
        ],

        // TAB 4: SYSTEM & ADMIN — Infra status grid (6 widgets)
        // Row 0 — Admin activity feed + Failed queue jobs
        // Row 1 — Disk usage + Cache status + Queue worker status
        // Row 2 — Request metrics, full width
        'system-admin' => [
            ['id' => 'recent_activity',     'x' => 0, 'y' => 0, 'w' => 8,  'h' => 4],
            ['id' => 'failed_queue_jobs',   'x' => 8, 'y' => 0, 'w' => 4,  'h' => 4],
            ['id' => 'disk_space',          'x' => 0, 'y' => 4, 'w' => 4,  'h' => 4],
            ['id' => 'cache_status',        'x' => 4, 'y' => 4, 'w' => 4,  'h' => 4],
            ['id' => 'queue_worker_status', 'x' => 8, 'y' => 4, 'w' => 4,  'h' => 4],
            ['id' => 'request_metrics',     'x' => 0, 'y' => 8, 'w' => 12, 'h' => 1],
        ],
    ];

    public function __construct(
        private readonly WidgetPreferenceService $preferences,
    ) {
    }

    /**
     * Guarantee the admin has at least one dashboard and return the active one.
     * Idempotent: seeds from legacy preferences (if any) or the role default.
     */
    public function ensureDefaultDashboard(Admin $admin): AdminDashboard
    {
        $existing = AdminDashboard::where('admin_id', $admin->id)->count();

        if ($existing === 0) {
            $legacyIds = $this->legacySeedWidgetIds($admin);

            if ($legacyIds !== null) {
                AdminDashboard::create([
                    'admin_id' => $admin->id,
                    'name' => 'My Dashboard',
                    'slug' => 'my-dashboard',
                    'layout' => $this->packLayout($legacyIds),
                    'is_default' => true,
                ]);
            } else {
                $tabs = $this->preferences->roleDefaultTabs($admin);
                $defaultName = array_key_first($tabs);

                foreach ($tabs as $name => $ids) {
                    $slug = Str::slug($name);
                    $layout = $this->blueprintLayoutFor($admin, $slug)
                        ?? $this->packLayout($ids);

                    AdminDashboard::create([
                        'admin_id' => $admin->id,
                        'name' => $name,
                        'slug' => $slug,
                        'layout' => $layout,
                        'is_default' => $name === $defaultName,
                    ]);
                }
            }
        }

        return $this->activeDashboard($admin);
    }

    public function activeDashboard(Admin $admin): AdminDashboard
    {
        $activeId = $this->preferences->getActiveDashboardId();

        if ($activeId !== null) {
            $dashboard = AdminDashboard::where('admin_id', $admin->id)->find($activeId);

            if ($dashboard) {
                return $dashboard;
            }
        }

        return AdminDashboard::where('admin_id', $admin->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->firstOrFail();
    }

    /** @return Collection<int, AdminDashboard> */
    public function listFor(Admin $admin): Collection
    {
        return AdminDashboard::where('admin_id', $admin->id)
            ->orderBy('id')
            ->get();
    }

    public function create(Admin $admin, string $name): AdminDashboard
    {
        $slug = $this->uniqueSlug($admin, $name);

        $dashboard = AdminDashboard::create([
            'admin_id' => $admin->id,
            'name' => $name,
            'slug' => $slug,
            'layout' => $this->packLayout($this->preferences->roleDefaultWidgetIds($admin)),
            'is_default' => false,
        ]);

        $this->preferences->saveActiveDashboardId($dashboard->id);

        return $dashboard;
    }

    public function rename(Admin $admin, int $dashboardId, string $name): void
    {
        $dashboard = AdminDashboard::where('admin_id', $admin->id)->findOrFail($dashboardId);

        $dashboard->update([
            'name' => $name,
            'slug' => $this->uniqueSlug($admin, $name, $dashboard->id),
        ]);
    }

    /** Deleting the last dashboard is not allowed. */
    public function delete(Admin $admin, int $dashboardId): bool
    {
        if (AdminDashboard::where('admin_id', $admin->id)->count() <= 1) {
            return false;
        }

        $dashboard = AdminDashboard::where('admin_id', $admin->id)->findOrFail($dashboardId);
        $wasDefault = $dashboard->is_default;
        $dashboard->delete();

        $next = AdminDashboard::where('admin_id', $admin->id)->orderByDesc('is_default')->orderBy('id')->first();

        if ($next) {
            if ($wasDefault) {
                $next->update(['is_default' => true]);
            }

            $this->preferences->saveActiveDashboardId($next->id);
        }

        return true;
    }

    public function switchTo(Admin $admin, int $dashboardId): AdminDashboard
    {
        $dashboard = AdminDashboard::where('admin_id', $admin->id)->findOrFail($dashboardId);

        $this->preferences->saveActiveDashboardId($dashboard->id);

        return $dashboard;
    }

    /**
     * Persist a gridstack layout. Every item is validated: the widget id must
     * exist in the registry AND be viewable by the admin's role; coordinates
     * are int-clamped to the 12-column grid. Unknown/forbidden items are
     * silently dropped rather than failing the whole save.
     */
    public function saveLayout(Admin $admin, int $dashboardId, array $items): array
    {
        $dashboard = AdminDashboard::where('admin_id', $admin->id)->findOrFail($dashboardId);

        $clean = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = (string) ($item['id'] ?? '');
            $config = WidgetPreferenceService::WIDGETS[$id] ?? null;

            if ($config === null || ! $admin->hasAnyRole($config['roles'])) {
                continue;
            }

            $w = min(self::MAX_CELL_SIZE, max(1, (int) ($item['w'] ?? $config['default_layout']['w'])));
            $h = min(self::MAX_CELL_SIZE, max(1, (int) ($item['h'] ?? $config['default_layout']['h'])));
            $x = min(self::GRID_COLUMNS - 1, max(0, (int) ($item['x'] ?? 0)));
            $y = max(0, (int) ($item['y'] ?? 0));

            if ($x + $w > self::GRID_COLUMNS) {
                $x = self::GRID_COLUMNS - $w;
            }

            $clean[$id] = ['id' => $id, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
        }

        $clean = array_values($clean);

        $dashboard->update(['layout' => $clean]);

        return $clean;
    }

    /**
     * Canvas items for rendering: active layout filtered to widgets the role
     * may view, with the widget class attached.
     *
     * @return list<array{id:string,class:string,x:int,y:int,w:int,h:int,minW:int,minH:int}>
     */
    public function canvasItems(Admin $admin, AdminDashboard $dashboard): array
    {
        $items = [];

        // Blueprint-governed dashboards may deliberately set a widget's h
        // below its bare registry default_layout (e.g. parts_inquiry at h:2
        // on Command Center, to sit compactly beside order_stats_overview)
        // — the floor below must defer to that intentional value instead of
        // clamping it back up, or every widget below it gets pushed down a
        // row. Falls back to the bare registry default for non-blueprint
        // (legacy/custom) dashboards, where it remains a genuine safety net
        // against a stale saved h smaller than the widget actually needs.
        $blueprintHeights = isset(self::TAB_BLUEPRINT_LAYOUTS[$dashboard->slug])
            ? collect(self::TAB_BLUEPRINT_LAYOUTS[$dashboard->slug])->pluck('h', 'id')
            : collect();

        foreach ($dashboard->layout ?? [] as $item) {
            $id = (string) ($item['id'] ?? '');
            $config = WidgetPreferenceService::WIDGETS[$id] ?? null;

            if ($config === null || ! $admin->hasAnyRole($config['roles'])) {
                continue;
            }

            if (! $config['class']::canView()) {
                continue;
            }

            $defaultLayout = $config['default_layout'];
            $heightFloor = $blueprintHeights->get($id, (int) $defaultLayout['h']);

            $actualW = (int) ($item['w'] ?? $defaultLayout['w']);
            $actualH = max((int) ($item['h'] ?? $defaultLayout['h']), $heightFloor);

            $items[] = [
                'id' => $id,
                'class' => $config['class'],
                'type' => $config['type'] ?? 'widget',
                'x' => (int) ($item['x'] ?? 0),
                'y' => (int) ($item['y'] ?? 0),
                'w' => $actualW,
                'h' => $actualH,
                'minW' => min($actualW, 4),
                'minH' => $heightFloor,
            ];
        }

        usort($items, fn ($a, $b) => [$a['y'], $a['x']] <=> [$b['y'], $b['x']]);

        return $items;
    }

    /**
     * Replace the active dashboard's widget set, keeping coordinates of
     * retained widgets and appending new ones below (row-major packed).
     */
    public function setWidgets(Admin $admin, int $dashboardId, array $ids): void
    {
        $dashboard = AdminDashboard::where('admin_id', $admin->id)->findOrFail($dashboardId);

        $current = collect($dashboard->layout ?? [])->keyBy('id');

        $kept = [];
        $new = [];

        foreach (array_unique($ids) as $id) {
            $config = WidgetPreferenceService::WIDGETS[$id] ?? null;

            if ($config === null || ! $admin->hasAnyRole($config['roles'])) {
                continue;
            }

            if ($current->has($id)) {
                $kept[] = $current->get($id);
            } else {
                $new[] = $id;
            }
        }

        $maxY = collect($kept)->map(fn ($i) => (int) $i['y'] + (int) $i['h'])->max() ?? 0;

        $dashboard->update([
            'layout' => array_merge($kept, $this->packLayout($new, $maxY)),
        ]);
    }

    /**
     * Returns the predefined blueprint layout for a tab slug, filtered to only
     * include widgets the given admin has permission to view.
     *
     * @return list<array{id:string,x:int,y:int,w:int,h:int}>|null
     */
    public function blueprintLayoutFor(Admin $admin, string $slug): ?array
    {
        $blueprint = self::TAB_BLUEPRINT_LAYOUTS[$slug] ?? null;

        if ($blueprint === null) {
            return null;
        }

        $layout = [];

        foreach ($blueprint as $item) {
            $id = $item['id'];
            $config = WidgetPreferenceService::WIDGETS[$id] ?? null;

            if ($config === null || ! $admin->hasAnyRole($config['roles'])) {
                continue;
            }

            $layout[] = $item;
        }

        return $layout;
    }

    /**
     * Row-major packing of widget ids into the 12-column grid using each
     * widget's registry default size.
     *
     * @return list<array{id:string,x:int,y:int,w:int,h:int}>
     */
    public function packLayout(array $ids, int $startY = 0): array
    {
        $layout = [];
        $x = 0;
        $y = $startY;
        $rowHeight = 0;

        foreach ($ids as $id) {
            if (! array_key_exists($id, WidgetPreferenceService::WIDGETS)) {
                continue;
            }

            ['w' => $w, 'h' => $h] = $this->preferences->defaultLayoutFor($id);

            if ($x + $w > self::GRID_COLUMNS) {
                $x = 0;
                $y += $rowHeight;
                $rowHeight = 0;
            }

            $layout[] = ['id' => $id, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];

            $x += $w;
            $rowHeight = max($rowHeight, $h);
        }

        return $layout;
    }

    /**
     * Widget ids for a first-time dashboard: honor legacy hidden/sort
     * preferences when present, otherwise the curated role default.
     *
     * @return list<string>
     */
    private function legacySeedWidgetIds(Admin $admin): ?array
    {
        $legacy = $this->preferences->getPreferences();

        if (empty($legacy)) {
            return null;
        }

        return array_values(array_map(
            fn (array $w) => $w['id'],
            array_filter(
                $this->preferences->getSortedWidgets(),
                fn (array $w) => ! $w['hidden'] && $admin->hasAnyRole(WidgetPreferenceService::WIDGETS[$w['id']]['roles']),
            ),
        ));
    }

    private function uniqueSlug(Admin $admin, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'dashboard';
        $slug = $base;
        $i = 2;

        while (
            AdminDashboard::where('admin_id', $admin->id)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
