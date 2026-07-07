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

        // Get products for this manufacturer (paginated). Eager-load only what the
        // view uses per row — the condition badge. (The manufacturer is already
        // known, and carModels is not referenced in the parts ledger.)
        $products = Product::query()
            ->where('manufacturer_id', $manufacturer->id)
            ->where('is_active', true)
            ->with('condition')
            ->orderBy('oem_number')
            ->paginate(settings('general.pagination_per_page', 20));

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
        $query = Manufacturer::query()
            ->where('is_active', true)
            ->with('logo'); // the ledger renders each brand's logo → avoid an N+1

        if ($request->filled('letter')) {
            $letter = strtoupper($request->letter);
            $query->where('name->en', 'like', $letter . '%');
        }

        $manufacturers = $query->orderBy('name->en')
            ->paginate(settings('general.pagination_per_page', 30))
            ->withQueryString();

        return view('frontend.manufacturer.index', [
            'manufacturers' => $manufacturers,
        ]);
    }
}