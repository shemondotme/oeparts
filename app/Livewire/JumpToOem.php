<?php

namespace App\Livewire;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Services\AdminNavService;
use App\Services\SearchService;
use Livewire\Component;

class JumpToOem extends Component
{
    public string $oem = '';

    public array $results = [];

    public string $searchType = 'none';

    public function updatedOem(): void
    {
        $normalized = trim($this->oem);

        if ($normalized === '') {
            $this->results = [];
            $this->searchType = 'none';

            return;
        }

        // log: false — this is an internal admin lookup, not a customer search;
        // it must not be counted in search_logs/failed_search_logs analytics.
        $result = app(SearchService::class)->search($this->oem, null, null, ['limit' => 8], log: false);

        $this->searchType = $result['search_type'];
        $this->results = collect($result['products'])
            ->map(fn (Product $product) => $this->mapProduct($product))
            ->filter()
            ->values()
            ->all();
    }

    public function selectFirst(): void
    {
        $target = $this->oem === ''
            ? ($this->recents()[0]['url'] ?? null)
            : ($this->results[0]['url'] ?? null);

        if ($target !== null) {
            $this->oem = '';
            $this->results = [];
            $this->redirect($target, navigate: true);
        }
    }

    /**
     * Idle-state list (empty $oem) — same permission-validated entries the
     * sidebar's "Recently Viewed" already shows, see AdminNavService::validRecent().
     */
    public function recents(): array
    {
        return AdminNavService::validRecent(auth('admin')->user());
    }

    private function mapProduct(Product $product): ?array
    {
        $url = ProductResource::getGlobalSearchResultUrl($product);

        if ($url === null) {
            return null;
        }

        return [
            'url' => $url,
            'oem' => $product->oem_number,
            'title' => is_array($product->name) ? trans_field($product->name, 'en') : null,
            'manufacturer' => $product->manufacturer && is_array($product->manufacturer->name)
                ? trans_field($product->manufacturer->name, 'en')
                : null,
        ];
    }

    public function render()
    {
        return view('livewire.jump-to-oem');
    }
}
