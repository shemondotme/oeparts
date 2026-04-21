<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CarModel;
use App\Models\Manufacturer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CarModelController extends Controller
{
    /**
     * Display a paginated list of car models.
     */
    public function index(Request $request)
    {
        $query = CarModel::with(['manufacturer'])
            ->withCount('products')
            ->latest('sort_order')
            ->latest('created_at');

        // Filter by manufacturer
        if ($request->filled('manufacturer_id')) {
            $query->where('manufacturer_id', $request->manufacturer_id);
        }

        // Filter by name
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        // Filter by year range
        if ($request->filled('year_from')) {
            $query->where('year_from', '>=', $request->year_from);
        }
        if ($request->filled('year_to')) {
            $query->where('year_to', '<=', $request->year_to);
        }

        // Filter by active status
        if ($request->filled('active_status')) {
            if ($request->active_status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->active_status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $carModels = $query->paginate(settings('admin.pagination.per_page', 25))
            ->withQueryString();

        $manufacturers = Manufacturer::orderBy('name')->get();

        return view('admin.catalog.car-models.index', compact('carModels', 'manufacturers'));
    }

    /**
     * Show the form for creating a new car model.
     */
    public function create()
    {
        $manufacturers = Manufacturer::orderBy('name')->get();

        return view('admin.catalog.car-models.create', compact('manufacturers'));
    }

    /**
     * Store a newly created car model in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'manufacturer_id' => 'required|exists:manufacturers,id',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:car_models,slug',
            'year_from' => 'nullable|integer|min:1900|max:2100',
            'year_to' => 'nullable|integer|min:1900|max:2100|gte:year_from',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // Generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $validated['is_active'] = $request->boolean('is_active');

        CarModel::create($validated);

        return redirect()->route('admin.catalog.car-models.index')
            ->with('success', __('Car model created successfully.'));
    }

    /**
     * Display the specified car model.
     */
    public function show(CarModel $carModel)
    {
        $carModel->load(['manufacturer', 'products']);

        return view('admin.catalog.car-models.show', compact('carModel'));
    }

    /**
     * Show the form for editing the specified car model.
     */
    public function edit(CarModel $carModel)
    {
        $manufacturers = Manufacturer::orderBy('name')->get();

        return view('admin.catalog.car-models.edit', compact('carModel', 'manufacturers'));
    }

    /**
     * Update the specified car model in storage.
     */
    public function update(Request $request, CarModel $carModel)
    {
        $validated = $request->validate([
            'manufacturer_id' => 'required|exists:manufacturers,id',
            'name' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('car_models', 'slug')->ignore($carModel->id),
            ],
            'year_from' => 'nullable|integer|min:1900|max:2100',
            'year_to' => 'nullable|integer|min:1900|max:2100|gte:year_from',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // Generate slug if not provided and name changed
        if (empty($validated['slug']) && $validated['name'] !== $carModel->name) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $validated['is_active'] = $request->boolean('is_active');

        $carModel->update($validated);

        return redirect()->route('admin.catalog.car-models.index')
            ->with('success', __('Car model updated successfully.'));
    }

    /**
     * Bulk activate car models.
     */
    public function bulkActivate(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'exists:car_models,id',
        ]);

        CarModel::whereIn('id', $request->ids)->update(['is_active' => true]);

        return redirect()->route('admin.catalog.car-models.index')
            ->with('success', __('Selected car models have been activated.'));
    }

    /**
     * Bulk deactivate car models.
     */
    public function bulkDeactivate(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'exists:car_models,id',
        ]);

        CarModel::whereIn('id', $request->ids)->update(['is_active' => false]);

        return redirect()->route('admin.catalog.car-models.index')
            ->with('success', __('Selected car models have been deactivated.'));
    }

    /**
     * Remove the specified car model from storage.
     */
    public function destroy(CarModel $carModel)
    {
        // Prevent deletion if there are associated products
        if ($carModel->products()->exists()) {
            return redirect()->route('admin.catalog.car-models.index')
                ->with('error', __('Cannot delete car model because it has associated products.'));
        }

        $carModel->delete();

        return redirect()->route('admin.catalog.car-models.index')
            ->with('success', __('Car model deleted successfully.'));
    }
}