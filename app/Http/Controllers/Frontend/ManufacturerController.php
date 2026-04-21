<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Http\Request;

class ManufacturerController extends Controller
{
    /**
     * Show manufacturer details and its products.
     *
     * Route: /{lang}/brand/{manufacturer}
     */
    public function show(Request $request, string $lang, string $manufacturer)
    {
        $manufacturer = Manufacturer::where('slug', $manufacturer)
            ->where('is_active', true)
            ->firstOrFail();

        // Get products for this manufacturer (paginated)
        $products = Product::query()
            ->where('manufacturer_id', $manufacturer->id)
            ->where('is_active', true)
            ->with(['manufacturer', 'carModels'])
            ->orderBy('oem_number')
            ->paginate(20);

        // Get car models for this manufacturer
        $carModels = $manufacturer->carModels()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('frontend.manufacturer.show', [
            'manufacturer' => $manufacturer,
            'products' => $products,
            'carModels' => $carModels,
        ]);
    }

    /**
     * List all manufacturers (alphabetical).
     *
     * Route: /{lang}/brands
     */
    public function index(Request $request, string $lang)
    {
        $manufacturers = Manufacturer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->paginate(30);

        return view('frontend.manufacturer.index', [
            'manufacturers' => $manufacturers,
        ]);
    }
}