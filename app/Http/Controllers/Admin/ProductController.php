<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCsvImport;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductCrossReference;
use App\Enums\ProductCondition;
use App\Services\OemNormalizerService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * Display a paginated list of products with filters.
     */
    public function index(Request $request)
    {
        $query = Product::with(['manufacturer'])
            ->latest('created_at');

        if ($request->filled('oem')) {
            $query->where('oem_number', 'like', '%' . $request->oem . '%');
        }

        if ($request->filled('normalized_oem')) {
            $query->where('normalized_oem', 'like', '%' . $request->normalized_oem . '%');
        }

        if ($request->filled('manufacturer_id')) {
            $query->where('manufacturer_id', $request->manufacturer_id);
        }

        if ($request->filled('condition') && $request->condition !== 'all') {
            $query->where('condition', $request->condition);
        }

        if ($request->filled('stock_status')) {
            if ($request->stock_status === 'in_stock') {
                $query->where('is_in_stock', true);
            } elseif ($request->stock_status === 'out_of_stock') {
                $query->where('is_in_stock', false);
            }
        }

        if ($request->filled('active_status')) {
            if ($request->active_status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->active_status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $products = $query->paginate(settings('admin.pagination.per_page', 25))
            ->withQueryString();

        $manufacturers = Manufacturer::orderBy('name')->get();
        $conditions    = ProductCondition::cases();

        return view('admin.catalog.products.index', compact('products', 'manufacturers', 'conditions'));
    }

    /**
     * Show details for a single product.
     */
    public function show(Product $product)
    {
        $product->load([
            'manufacturer',
            'carModels.manufacturer',
            'crossReferences',
            'inventoryLogs' => fn ($q) => $q->with('admin')->latest('created_at')->limit(20),
        ]);

        return view('admin.catalog.products.show', compact('product'));
    }

    /**
     * Show the form for creating a new product.
     */
    public function create()
    {
        $manufacturers = Manufacturer::orderBy('name')->get();
        $conditions    = ProductCondition::cases();
        $carModels     = \App\Models\CarModel::with('manufacturer')->orderBy('name')->get();

        return view('admin.catalog.products.create', compact('manufacturers', 'conditions', 'carModels'));
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'manufacturer_id'   => 'required|exists:manufacturers,id',
            'oem_number'        => 'required|string|max:100|unique:products,oem_number',
            'name'              => 'required|array',
            'name.en'           => 'required|string|max:255',
            'name.de'           => 'nullable|string|max:255',
            'name.lt'           => 'nullable|string|max:255',
            'name.fr'           => 'nullable|string|max:255',
            'name.es'           => 'nullable|string|max:255',
            'description'       => 'nullable|array',
            'description.en'    => 'nullable|string',
            'description.de'    => 'nullable|string',
            'description.lt'    => 'nullable|string',
            'description.fr'    => 'nullable|string',
            'description.es'    => 'nullable|string',
            'condition'         => ['required', Rule::enum(ProductCondition::class)],
            'price'             => 'required|numeric|min:0|max:999999.99',
            'delivery_time'     => 'nullable|string|max:50',
            'moq'               => 'nullable|integer|min:1',
            'is_in_stock'       => 'boolean',
            'is_active'         => 'boolean',
            'car_model_ids'     => 'nullable|array',
            'car_model_ids.*'   => 'exists:car_models,id',
            'cross_references'  => 'nullable|array',
            'cross_references.*'=> 'string|max:100',
        ]);

        $validated['name']        = array_filter($validated['name'] ?? [], fn($v) => $v !== null && $v !== '');
        $validated['description'] = array_filter($validated['description'] ?? [], fn($v) => $v !== null && $v !== '');
        if (empty($validated['description'])) {
            $validated['description'] = null;
        }

        $validated['normalized_oem'] = app(OemNormalizerService::class)->normalize($validated['oem_number']);
        $validated['is_in_stock']    = $request->boolean('is_in_stock');
        $validated['is_active']      = $request->boolean('is_active');

        $carModelIds     = $validated['car_model_ids'] ?? [];
        $crossReferences = $validated['cross_references'] ?? [];
        unset($validated['car_model_ids'], $validated['cross_references']);

        $product = Product::create($validated);

        if (!empty($carModelIds)) {
            $product->carModels()->sync($carModelIds);
        }

        $this->syncCrossReferences($product, $crossReferences);

        return redirect()->route('admin.catalog.products.index')
            ->with('success', __('Product created successfully.'));
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product)
    {
        $product->load(['carModels', 'crossReferences']);

        $manufacturers = Manufacturer::orderBy('name')->get();
        $conditions    = ProductCondition::cases();
        $carModels     = \App\Models\CarModel::with('manufacturer')->orderBy('name')->get();

        return view('admin.catalog.products.edit', compact('product', 'manufacturers', 'conditions', 'carModels'));
    }

    /**
     * Update the specified product in storage.
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'manufacturer_id'   => 'required|exists:manufacturers,id',
            'oem_number'        => [
                'required', 'string', 'max:100',
                Rule::unique('products', 'oem_number')->ignore($product->id),
            ],
            'name'              => 'required|array',
            'name.*'            => 'required|string|max:255',
            'description'       => 'nullable|array',
            'description.*'     => 'nullable|string',
            'condition'         => ['required', Rule::enum(ProductCondition::class)],
            'price'             => 'required|numeric|min:0|max:999999.99',
            'delivery_time'     => 'nullable|string|max:50',
            'moq'               => 'nullable|integer|min:1',
            'is_in_stock'       => 'boolean',
            'is_active'         => 'boolean',
            'car_model_ids'     => 'nullable|array',
            'car_model_ids.*'   => 'exists:car_models,id',
            'cross_references'  => 'nullable|array',
            'cross_references.*'=> 'string|max:100',
        ]);

        if ($validated['oem_number'] !== $product->oem_number) {
            $validated['normalized_oem'] = app(OemNormalizerService::class)->normalize($validated['oem_number']);
        }

        $validated['is_in_stock'] = $request->boolean('is_in_stock');
        $validated['is_active']   = $request->boolean('is_active');

        $carModelIds     = $validated['car_model_ids'] ?? [];
        $crossReferences = $validated['cross_references'] ?? [];
        unset($validated['car_model_ids'], $validated['cross_references']);

        $product->update($validated);
        $product->carModels()->sync($carModelIds);
        $this->syncCrossReferences($product, $crossReferences);

        return redirect()->route('admin.catalog.products.index')
            ->with('success', __('Product updated successfully.'));
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('admin.catalog.products.index')
            ->with('success', __('Product deleted successfully.'));
    }

    /**
     * Handle inline edit updates (AJAX).
     */
    public function inlineUpdate(Request $request, Product $product)
    {
        $field = $request->input('field');
        $value = $request->input('value');

        $allowedFields = ['price', 'delivery_time', 'moq', 'is_in_stock', 'is_active'];
        if (!in_array($field, $allowedFields)) {
            return response()->json(['success' => false, 'message' => __('Invalid field.')], 422);
        }

        $validationRules = [
            'price'         => 'numeric|min:0|max:999999.99',
            'delivery_time' => 'string|max:50',
            'moq'           => 'integer|min:1',
            'is_in_stock'   => 'boolean',
            'is_active'     => 'boolean',
        ];

        $validator = \Validator::make([$field => $value], [$field => $validationRules[$field]]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        if ($field === 'is_in_stock' || $field === 'is_active') {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        $product->update([$field => $value]);

        return response()->json(['success' => true, 'new_value' => $value]);
    }

    /**
     * Bulk delete products.
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'exists:products,id',
        ]);

        Product::whereIn('id', $request->ids)->delete();

        return redirect()->route('admin.catalog.products.index')
            ->with('success', __('Selected products have been deleted.'));
    }

    /**
     * Bulk activate products.
     */
    public function bulkActivate(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'exists:products,id',
        ]);

        Product::whereIn('id', $request->ids)->update(['is_active' => true]);

        return redirect()->route('admin.catalog.products.index')
            ->with('success', __('Selected products have been activated.'));
    }

    /**
     * Bulk deactivate products.
     */
    public function bulkDeactivate(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'exists:products,id',
        ]);

        Product::whereIn('id', $request->ids)->update(['is_active' => false]);

        return redirect()->route('admin.catalog.products.index')
            ->with('success', __('Selected products have been deactivated.'));
    }

    /**
     * Show CSV import form.
     */
    public function importForm()
    {
        return view('admin.catalog.products.import');
    }

    /**
     * Queue CSV import job.
     */
    public function import(Request $request)
    {
        $request->validate([
            'csv_file'        => 'required|file|mimes:csv,txt|max:102400',
            'update_existing' => 'boolean',
        ]);

        $storagePath = $request->file('csv_file')->storeAs(
            'imports',
            'products_' . now()->format('YmdHis') . '_' . uniqid() . '.csv',
        );

        dispatch(new ProcessCsvImport(
            storagePath:    $storagePath,
            adminId:        auth('admin')->id(),
            updateExisting: $request->boolean('update_existing'),
        ))->onQueue('default');

        return redirect()->route('admin.catalog.products.index')
            ->with('success', __('Import queued. New and updated products will appear shortly. Errors are recorded in Bulk Update Logs.'));
    }

    /**
     * Stream a pre-formatted CSV template for download.
     */
    public function csvTemplate()
    {
        $columns = [
            'oem_number', 'manufacturer_slug', 'condition_slug', 'price', 'is_in_stock',
            'delivery_time', 'moq',
            'name_en', 'name_de', 'name_lt', 'name_fr', 'name_es',
            'description_en', 'description_de', 'description_lt', 'description_fr', 'description_es',
            'cross_oem_numbers',
        ];

        $exampleRow = [
            '0252225577', 'bosch', 'new', '8.99', '1',
            '3-5 days', '1',
            'Bosch Spark Plug FR7DC+', '', '', '', '',
            'Genuine Bosch spark plug for petrol engines.', '', '', '', '',
            '0242229799|0242240650',
        ];

        $callback = function () use ($columns, $exampleRow) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            fputcsv($out, $exampleRow);
            fclose($out);
        };

        return response()->stream($callback, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="products_import_template.csv"',
        ]);
    }

    /**
     * Sync cross-reference OEM numbers for a product.
     * Deletes all existing refs and re-inserts the supplied ones (normalized).
     */
    private function syncCrossReferences(Product $product, array $rawOems): void
    {
        $product->crossReferences()->delete();

        $normalizer = app(OemNormalizerService::class);
        $seen       = [];

        foreach ($rawOems as $raw) {
            $raw = trim((string) $raw);
            if ($raw === '') {
                continue;
            }
            $normalized = $normalizer->normalize($raw);
            if (isset($seen[$normalized])) {
                continue; // skip duplicates
            }
            $seen[$normalized] = true;

            ProductCrossReference::create([
                'product_id'          => $product->id,
                'cross_oem_number'    => $raw,
                'normalized_cross_oem'=> $normalized,
            ]);
        }
    }
}
