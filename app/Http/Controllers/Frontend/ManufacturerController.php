<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            ->with('logo')
            ->firstOrFail();

        // Eager-load only what the view uses per row — the condition badge.
        // (The manufacturer is already known, and carModels is not
        // referenced in the parts ledger.)
        $products = Product::query()
            ->where('manufacturer_id', $manufacturer->id)
            ->where('is_active', true)
            ->with('condition')
            ->orderBy('oem_number')
            ->paginate(settings('general.pagination_per_page', 20));

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
        // The letter-filter and A-Z sort used to always read name->en
        // regardless of $lang, while the view groups/displays manufacturers
        // by trans_field($m->name) (locale-aware) — non-English visitors saw
        // letter groups built from the wrong language entirely, so clicking
        // e.g. "Z" could return results starting with any letter in their
        // own locale's name. $lang is already constrained to this whitelist
        // by the route (`where(['lang' => 'en|de|lt|fr|es'])`); re-checked
        // here since it's interpolated into raw SQL below.
        $lang = in_array($lang, ['en', 'de', 'lt', 'fr', 'es'], true) ? $lang : 'en';

        // COALESCE to English mirrors trans_field()'s own fallback, so a
        // manufacturer missing a translation for this locale doesn't just
        // vanish from the letter index or sort to an undefined position.
        // MySQL (production) needs JSON_UNQUOTE to strip the quotes
        // JSON_EXTRACT leaves on a scalar; SQLite's json_extract() (used
        // under the test suite) already returns an unquoted scalar and has
        // no JSON_UNQUOTE equivalent.
        $localizedName = DB::connection()->getDriverName() === 'sqlite'
            ? "COALESCE(json_extract(name, '$.\"{$lang}\"'), json_extract(name, '$.en'))"
            : "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"{$lang}\"')), JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')))";

        $query = Manufacturer::query()
            ->where('is_active', true)
            ->with('logo'); // the ledger renders each brand's logo → avoid an N+1

        if ($request->filled('letter')) {
            $letter = strtoupper($request->letter);
            $query->whereRaw("{$localizedName} LIKE ?", [$letter . '%']);
        }

        $manufacturers = $query->orderByRaw($localizedName)
            ->paginate(settings('general.pagination_per_page', 30))
            ->withQueryString();

        return view('frontend.manufacturer.index', [
            'manufacturers' => $manufacturers,
        ]);
    }
}