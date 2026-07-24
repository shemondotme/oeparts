<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\CarModelApiResource;
use App\Http\Resources\CategoryApiResource;
use App\Http\Resources\ManufacturerApiResource;
use App\Http\Resources\ProductApiResource;
use App\Http\Resources\ProductCrossReferenceResource;
use App\Models\CarModel;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends BaseApiController
{
    public function __construct(
        private SearchService $searchService
    ) {}

    /**
     * GET /api/v1/categories
     */
    public function categories(Request $request): JsonResponse
    {
        $categories = Category::with('children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return $this->successResponse(
            CategoryApiResource::collection($categories)
        );
    }

    /**
     * GET /api/v1/categories/{slug}
     */
    public function category(string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)
            ->with('children')
            ->first();

        if (!$category) {
            return $this->errorResponse('Category not found.', null, 404);
        }

        return $this->successResponse(new CategoryApiResource($category));
    }

    /**
     * GET /api/v1/manufacturers
     */
    public function manufacturers(Request $request): JsonResponse
    {
        $query = Manufacturer::where('is_active', true)->orderBy('sort_order');

        if ($request->has('country')) {
            $query->where('country_code', strtoupper($request->input('country')));
        }

        return $this->successResponse(
            ManufacturerApiResource::collection($query->get())
        );
    }

    /**
     * GET /api/v1/manufacturers/{slug}
     */
    public function manufacturer(string $slug): JsonResponse
    {
        $manufacturer = Manufacturer::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$manufacturer) {
            return $this->errorResponse('Manufacturer not found.', null, 404);
        }

        return $this->successResponse(new ManufacturerApiResource($manufacturer));
    }

    /**
     * GET /api/v1/car-models
     */
    public function carModels(Request $request): JsonResponse
    {
        $query = CarModel::where('is_active', true)
            ->with('manufacturer')
            ->orderBy('manufacturer_id')
            ->orderBy('sort_order');

        if ($request->has('manufacturer_id')) {
            $query->where('manufacturer_id', $request->input('manufacturer_id'));
        }

        if ($request->has('year')) {
            $year = (int) $request->input('year');
            $query->where('year_from', '<=', $year)
                  ->where(function ($q) use ($year) {
                      $q->whereNull('year_to')->orWhere('year_to', '>=', $year);
                  });
        }

        return $this->successResponse(
            CarModelApiResource::collection($query->paginate(50))
        );
    }

    /**
     * GET /api/v1/car-models/{id}
     */
    public function carModel(int $id): JsonResponse
    {
        $carModel = CarModel::where('id', $id)
            ->where('is_active', true)
            ->with('manufacturer')
            ->first();

        if (!$carModel) {
            return $this->errorResponse('Car model not found.', null, 404);
        }

        return $this->successResponse(new CarModelApiResource($carModel));
    }

    /**
     * GET /api/v1/parts — search parts by query params.
     */
    public function parts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'oem' => 'nullable|string|max:100',
            'manufacturer_id' => 'nullable|integer|exists:manufacturers,id',
            'car_model_id' => 'nullable|integer|exists:car_models,id',
            'in_stock' => 'nullable|boolean',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'sort' => 'nullable|in:price_asc,price_desc,name,oem',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Product::where('is_active', true)
            ->with(['manufacturer', 'condition']);

        if (!empty($validated['oem'])) {
            $normalized = preg_replace('/[^A-Z0-9]/i', '', strtoupper($validated['oem']));
            $query->where('normalized_oem', 'LIKE', "%{$normalized}%");
        }

        if (!empty($validated['q']) && empty($validated['oem'])) {
            $query->where(function ($q) use ($validated) {
                $term = $validated['q'];
                $q->where('name', 'LIKE', "%{$term}%")
                  ->orWhere('oem_number', 'LIKE', "%{$term}%")
                  ->orWhere('normalized_oem', 'LIKE', "%" . strtoupper($term) . "%");
            });
        }

        if (!empty($validated['manufacturer_id'])) {
            $query->where('manufacturer_id', $validated['manufacturer_id']);
        }

        if (!empty($validated['car_model_id'])) {
            $query->whereHas('carModels', function ($q) use ($validated) {
                $q->where('car_models.id', $validated['car_model_id']);
            });
        }

        if (isset($validated['in_stock'])) {
            $query->where('is_in_stock', (bool) $validated['in_stock']);
        }

        if (!empty($validated['min_price'])) {
            $query->where('price', '>=', $validated['min_price']);
        }
        if (!empty($validated['max_price'])) {
            $query->where('price', '<=', $validated['max_price']);
        }

        $query = match ($validated['sort'] ?? null) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'oem' => $query->orderBy('normalized_oem'),
            default => $query->orderBy('normalized_oem'),
        };

        $perPage = $validated['per_page'] ?? 25;

        return $this->paginatedResponse(
            $query->paginate($perPage),
            ProductApiResource::class
        );
    }

    /**
     * GET /api/v1/parts/{oem} — get part by OEM number.
     */
    public function partByOem(string $oem): JsonResponse
    {
        $normalized = preg_replace('/[^A-Z0-9]/i', '', strtoupper($oem));

        $product = Product::where('normalized_oem', $normalized)
            ->where('is_active', true)
            ->with(['manufacturer', 'condition', 'crossReferences'])
            ->first();

        if (!$product) {
            return $this->errorResponse('Part not found.', null, 404);
        }

        return $this->successResponse(new ProductApiResource($product));
    }

    /**
     * GET /api/v1/parts/{oem}/supersessions
     */
    public function supersessions(string $oem): JsonResponse
    {
        $normalized = preg_replace('/[^A-Z0-9]/i', '', strtoupper($oem));

        $product = Product::where('normalized_oem', $normalized)->first();
        if (!$product) {
            return $this->errorResponse('Part not found.', null, 404);
        }

        // Follow supersession chain
        $chain = [];
        $current = $product;
        $visited = [$product->id];

        while ($current->superseded_by_id) {
            $next = Product::find($current->superseded_by_id);
            if (!$next || in_array($next->id, $visited)) {
                break; // prevent infinite loops
            }
            $chain[] = new ProductApiResource($next);
            $visited[] = $next->id;
            $current = $next;
        }

        return $this->successResponse([
            'oem' => $oem,
            'supersessions' => $chain,
        ]);
    }

    /**
     * GET /api/v1/parts/{oem}/cross-references
     */
    public function crossReferences(string $oem): JsonResponse
    {
        $normalized = preg_replace('/[^A-Z0-9]/i', '', strtoupper($oem));

        $product = Product::where('normalized_oem', $normalized)
            ->where('is_active', true)
            ->with('crossReferences')
            ->first();

        if (!$product) {
            return $this->errorResponse('Part not found.', null, 404);
        }

        return $this->successResponse([
            'oem' => $oem,
            'cross_references' => ProductCrossReferenceResource::collection($product->crossReferences),
        ]);
    }

    /**
     * GET /api/v1/product-details/{id}
     */
    public function productDetails(int $id): JsonResponse
    {
        $product = Product::where('id', $id)
            ->where('is_active', true)
            ->with(['manufacturer', 'condition', 'crossReferences', 'carModels'])
            ->first();

        if (!$product) {
            return $this->errorResponse('Product not found.', null, 404);
        }

        return $this->successResponse(new ProductApiResource($product));
    }
}
