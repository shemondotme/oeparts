<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Services\CacheService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ManufacturerController extends Controller
{
    public function __construct(
        private CacheService $cacheService,
    ) {}

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

        // Eager-load only what the view uses per row — the condition badge
        // and (for the ItemList JSON-LD's image field) the featured image.
        // (The manufacturer is already known, and carModels is not
        // referenced in the parts ledger.)
        $products = Product::query()
            ->where('manufacturer_id', $manufacturer->id)
            ->where('is_active', true)
            ->with(['condition', 'featuredImage'])
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
        // here since it feeds the sort/filter below.
        $lang = in_array($lang, ['en', 'de', 'lt', 'fr', 'es'], true) ? $lang : 'en';

        // Was a fresh, uncached query on every visit — a locale-aware
        // ORDER BY on a JSON_EXTRACT expression (can't use an index) plus a
        // LIKE-filtered paginate() — on a page linked from the nav on every
        // single page of the site. Now: fetch (and cache) every active
        // manufacturer ONCE, locale-independent, and do the locale-aware
        // sort/letter-filter/pagination in PHP on that cached collection —
        // avoids both the non-indexable DB sort and a combinatorial
        // explosion of per-locale/per-letter/per-page cache keys.
        $all = $this->cacheService->rememberAllActiveManufacturers(
            fn () => Manufacturer::where('is_active', true)->with('logo')->get()
        );

        $sorted = $all->sortBy(fn (Manufacturer $m) => trans_field($m->name, $lang))->values();

        if ($request->filled('letter')) {
            $letter = strtoupper($request->letter);
            $sorted = $sorted->filter(
                fn (Manufacturer $m) => str_starts_with(strtoupper(trans_field($m->name, $lang)), $letter)
            )->values();
        }

        $perPage = (int) settings('general.pagination_per_page', 30);
        $page = (int) $request->get('page', 1);

        $manufacturers = (new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
            ['path' => $request->url()]
        ))->withQueryString();

        return view('frontend.manufacturer.index', [
            'manufacturers' => $manufacturers,
        ]);
    }
}