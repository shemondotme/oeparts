<?php

namespace App\Livewire;

use App\Services\AdminNavService;
use Illuminate\Support\Collection;
use Livewire\Component;

class SidebarPinnedNav extends Component
{
    public function pin(string $key): void
    {
        $admin = auth('admin')->user();

        if (! $admin) {
            return;
        }

        $item = $this->availableItems()->firstWhere('key', $key);

        if (! $item) {
            return;
        }

        AdminNavService::pin($admin, $item['key'], $item['label'], $item['url'], $item['icon']);
    }

    public function unpin(string $key): void
    {
        $admin = auth('admin')->user();

        if (! $admin) {
            return;
        }

        AdminNavService::unpin($admin, $key);
    }

    /**
     * Flatten Filament's already permission-filtered navigation into a flat
     * list keyed by URL. Rebuilt fresh on every render -- pin/unpin and the
     * rendered pinned list always reflect the admin's CURRENT access, so a
     * stale pinned entry for a since-revoked resource silently stops
     * appearing instead of leaking a dead/forbidden link.
     *
     * @return Collection<int, array{key: string, label: string, url: string, icon: ?string, group: ?string}>
     */
    protected function availableItems(): Collection
    {
        return collect(filament()->getNavigation())
            ->flatMap(function ($group) {
                $groupLabel = $group->getLabel();

                return collect($group->getItems())->map(fn ($item) => [
                    'key' => $item->getUrl(),
                    'label' => $item->getLabel(),
                    'url' => $item->getUrl(),
                    'icon' => $item->getIcon(),
                    'group' => $groupLabel,
                ]);
            })
            ->unique('key')
            ->values();
    }

    /**
     * @return array<int, array{key: string, label: string, url: string, icon: ?string, group: ?string}>
     */
    protected function pinnedItems(\App\Models\Admin $admin): array
    {
        $available = $this->availableItems()->keyBy('key');

        return collect(AdminNavService::pinned($admin))
            ->filter(fn ($pin) => $available->has($pin['key'] ?? null))
            ->map(fn ($pin) => $available[$pin['key']])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key: string, label: string, url: string, icon: ?string, group: ?string}>
     */
    protected function availableToPinItems(\App\Models\Admin $admin): array
    {
        $pinnedKeys = collect(AdminNavService::pinned($admin))->pluck('key')->all();

        return $this->availableItems()
            ->reject(fn ($item) => in_array($item['key'], $pinnedKeys, true))
            ->values()
            ->all();
    }

    public function render()
    {
        $admin = auth('admin')->user();

        return view('livewire.sidebar-pinned-nav', [
            'pinned' => $admin ? $this->pinnedItems($admin) : [],
            'availableToPin' => $admin ? $this->availableToPinItems($admin) : [],
        ]);
    }
}
