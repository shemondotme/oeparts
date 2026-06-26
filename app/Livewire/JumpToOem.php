<?php

namespace App\Livewire;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Services\OemNormalizerService;
use Livewire\Component;

class JumpToOem extends Component
{
    public string $oem = '';

    public ?string $errorMessage = null;

    public function jump(): void
    {
        $admin = auth('admin')->user();

        if (! $admin) {
            return;
        }

        $normalized = app(OemNormalizerService::class)->normalize($this->oem);

        $this->errorMessage = null;

        if ($normalized === '') {
            return;
        }

        $product = Product::query()->where('normalized_oem', $normalized)->first();

        if (! $product) {
            $this->errorMessage = "No exact match for \"{$this->oem}\".";

            return;
        }

        $url = ProductResource::getGlobalSearchResultUrl($product);

        if ($url === null) {
            $this->errorMessage = "You don't have access to that product.";

            return;
        }

        $this->oem = '';

        $this->redirect($url, navigate: true);
    }

    public function render()
    {
        return view('livewire.jump-to-oem');
    }
}
