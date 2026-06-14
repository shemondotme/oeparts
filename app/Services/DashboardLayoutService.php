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
                    AdminDashboard::create([
                        'admin_id' => $admin->id,
                        'name' => $name,
                        'slug' => Str::slug($name),
                        'layout' => $this->packLayout($ids),
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

            $items[] = [
                'id' => $id,
                'class' => $config['class'],
                'type' => $config['type'] ?? 'widget',
                'x' => (int) ($item['x'] ?? 0),
                'y' => (int) ($item['y'] ?? 0),
                'w' => (int) ($item['w'] ?? $defaultLayout['w']),
                'h' => max((int) ($item['h'] ?? $defaultLayout['h']), (int) $defaultLayout['h']),
                'minW' => min((int) $defaultLayout['w'], 4),
                'minH' => (int) $defaultLayout['h'],
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
