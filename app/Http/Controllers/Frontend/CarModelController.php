<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Manufacturer;
use App\Models\CarModel;
use App\Models\Product;
use Illuminate\Http\Request;

class CarModelController extends Controller
{
    /**
     * Show car model details and its products.
     *
     * Route: /{lang}/brand/{manufacturer}/{model}
     */
    public function show(Request $request, string $lang, string $manufacturer, string $model)
    {
        $manufacturer = Manufacturer::where('slug', $manufacturer)
            ->where('is_active', true)
            ->firstOrFail();

        $carModel = CarModel::where('slug', $model)
            ->where('manufacturer_id', $manufacturer->id)
            ->where('is_active', true)
            ->firstOrFail();

        // Get products for this car model
        $products = Product::query()
            ->whereHas('carModels', function ($query) use ($carModel) {
                $query->where('car_model_id', $carModel->id);
            })
            ->where('is_active', true)
            ->with(['manufacturer', 'carModels'])
            ->orderBy('oem_number')
            ->paginate(20);

        // Get other car models from same manufacturer
        $otherModels = CarModel::query()
            ->where('manufacturer_id', $manufacturer->id)
            ->where('is_active', true)
            ->where('id', '!=', $carModel->id)
            ->orderBy('name')
            ->limit(10)
            ->get();

        return view('frontend.car-model.show', [
            'manufacturer' => $manufacturer,
            'carModel' => $carModel,
            'products' => $products,
            'otherModels' => $otherModels,
        ]);
    }

    /**
     * List all car models for a manufacturer.
     *
     * Route: /{lang}/brand/{manufacturer}/models
     */
    public function index(Request $request, string $lang, string $manufacturer)
    {
        $manufacturer = Manufacturer::where('slug', $manufacturer)
            ->where('is_active', true)
            ->firstOrFail();

        $carModels = CarModel::query()
            ->where('manufacturer_id', $manufacturer->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->paginate(30);

        return view('frontend.car-model.index', [
            'manufacturer' => $manufacturer,
            'carModels' => $carModels,
        ]);
    }
}