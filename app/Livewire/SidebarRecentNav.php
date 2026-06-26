<?php

namespace App\Livewire;

use App\Models\Admin;
use App\Services\AdminNavService;
use Illuminate\Support\Collection;
use Livewire\Component;

class SidebarRecentNav extends Component
{
    /**
     * Flatten Filament's already permission-filtered navigation into a flat
     * list keyed by URL -- same helper shape as SidebarPinnedNav::availableItems(),
     * used here only to validate that a recent entry's underlying page is
     * still reachable, not to re-derive its display label (see recentItems()).
     */
    protected function availableItems(): Collection
    {
        // Exclude the Dashboard/home URL: it's the panel root (e.g. "/admin"),
        // a string-prefix of every other admin URL, which would make the
        // str_starts_with() sub-path check in recentItems() below incorrectly
        // treat Dashboard as the "parent page" of any recent entry.
        $homeUrl = filament()->getHomeUrl();

        return collect(filament()->getNavigation())
            ->flatMap(fn ($group) => collect($group->getItems())->map(fn ($item) => [
                'url' => $item->getUrl(),
                'icon' => $item->getIcon(),
            ]))
            ->reject(fn ($item) => $item['url'] === $homeUrl)
            ->unique('url')
            ->values();
    }

    /**
     * Recents store a record-specific snapshot label (e.g. "Edit Bosch Brake
     * Pad Set") that has no live nav-tree equivalent to re-derive -- unlike
     * SidebarPinnedNav, which re-derives its label fresh every render because
     * pinned labels ARE static nav-tree labels. Re-deriving isn't applicable
     * here (Edit/View pages aren't separately registered in the nav tree --
     * only their List page is), so this validates at the RESOURCE/PAGE level
     * instead: an entry is dropped if no nav item's URL is, or is a parent
     * path of, the entry's URL -- catching permission revocation (the
     * security-relevant half of CLAUDE.md rule #28) without trying to
     * re-derive a per-record label the underlying API doesn't provide.
     *
     * @return array<int, array{key: string, label: string, url: string, group: ?string, icon: ?string}>
     */
    protected function recentItems(Admin $admin): array
    {
        $available = $this->availableItems();

        return collect(AdminNavService::recent($admin))
            ->map(function ($item) use ($available) {
                $url = $item['url'] ?? null;
                $match = $url ? $available->first(fn ($nav) => $url === $nav['url'] || str_starts_with($url, $nav['url'] . '/')) : null;

                if (! $match) {
                    return null;
                }

                $item['icon'] = $match['icon'];

                return $item;
            })
            ->filter()
            ->values()
            ->all();
    }

    public function render()
    {
        $admin = auth('admin')->user();

        return view('livewire.sidebar-recent-nav', [
            'recent' => $admin ? $this->recentItems($admin) : [],
        ]);
    }
}
