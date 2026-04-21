<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Manufacturer;
use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ManufacturerController extends Controller
{
    /**
     * Display a paginated list of manufacturers.
     */
    public function index(Request $request)
    {
        $query = Manufacturer::with(['logo'])
            ->withCount('products')
            ->latest('sort_order')
            ->latest('created_at');

        if ($request->filled('name')) {
            $query->whereRaw("LOWER(name) LIKE ?", ['%' . strtolower($request->name) . '%']);
        }

        if ($request->filled('country_code')) {
            $query->where('country_code', $request->country_code);
        }

        if ($request->filled('active_status')) {
            if ($request->active_status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->active_status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($request->filled('oem_verified')) {
            if ($request->oem_verified === 'verified') {
                $query->where('is_verified_oem', true);
            } elseif ($request->oem_verified === 'not_verified') {
                $query->where('is_verified_oem', false);
            }
        }

        $manufacturers = $query->paginate(settings('admin.pagination.per_page', 25))
            ->withQueryString();

        $countries = config('countries', []);

        return view('admin.catalog.manufacturers.index', compact('manufacturers', 'countries'));
    }

    /**
     * Show the form for creating a new manufacturer.
     */
    public function create()
    {
        $countries = config('countries', []);

        return view('admin.catalog.manufacturers.create', compact('countries'));
    }

    /**
     * Store a newly created manufacturer in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'slug'            => 'nullable|string|max:255|unique:manufacturers,slug',
            'country_code'    => 'nullable|string|size:2',
            'logo'            => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
            'is_active'       => 'boolean',
            'is_verified_oem' => 'boolean',
            'sort_order'      => 'nullable|integer|min:0',
        ]);

        // Wrap plain name string as multilang JSON
        $plainName = $validated['name'];
        $validated['name'] = ['en' => $plainName];

        // Generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($plainName);
        }

        $validated['is_active']       = $request->boolean('is_active');
        $validated['is_verified_oem'] = $request->boolean('is_verified_oem');

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $file     = $request->file('logo');
            $ext      = $file->getClientOriginalExtension();
            $basename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $filename = $basename . '-' . time() . '.' . $ext;
            $path     = $file->storeAs('media/logos', $filename, 'public');

            $media = MediaFile::create([
                'file_name'   => $filename,
                'file_path'   => $path,
                'file_url'    => Storage::disk('public')->url($path),
                'mime_type'   => $file->getMimeType(),
                'size'        => $file->getSize(),
                'uploaded_by' => auth('admin')->id(),
            ]);
            $validated['logo_id'] = $media->id;
        }

        unset($validated['logo']);

        Manufacturer::create($validated);

        return redirect()->route('admin.catalog.manufacturers.index')
            ->with('success', __('Manufacturer created successfully.'));
    }

    /**
     * Display the specified manufacturer.
     */
    public function show(Manufacturer $manufacturer)
    {
        $manufacturer->load(['logo', 'products', 'carModels']);

        return view('admin.catalog.manufacturers.show', compact('manufacturer'));
    }

    /**
     * Show the form for editing the specified manufacturer.
     */
    public function edit(Manufacturer $manufacturer)
    {
        $countries = config('countries', []);

        return view('admin.catalog.manufacturers.edit', compact('manufacturer', 'countries'));
    }

    /**
     * Update the specified manufacturer in storage.
     */
    public function update(Request $request, Manufacturer $manufacturer)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'slug'            => [
                'nullable', 'string', 'max:255',
                Rule::unique('manufacturers', 'slug')->ignore($manufacturer->id),
            ],
            'country_code'    => 'nullable|string|size:2',
            'logo'            => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
            'remove_logo'     => 'boolean',
            'is_active'       => 'boolean',
            'is_verified_oem' => 'boolean',
            'sort_order'      => 'nullable|integer|min:0',
        ]);

        // Regenerate slug only if name changed and slug not manually supplied
        $plainName = $validated['name'];
        $validated['name'] = ['en' => $plainName];
        $currentEnName = trans_field($manufacturer->name, 'en');
        if (empty($validated['slug']) && $plainName !== $currentEnName) {
            $validated['slug'] = Str::slug($plainName);
        }

        $validated['is_active']       = $request->boolean('is_active');
        $validated['is_verified_oem'] = $request->boolean('is_verified_oem');

        // Handle logo remove / upload
        if ($request->boolean('remove_logo')) {
            $validated['logo_id'] = null;
        } elseif ($request->hasFile('logo')) {
            $file     = $request->file('logo');
            $ext      = $file->getClientOriginalExtension();
            $basename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $filename = $basename . '-' . time() . '.' . $ext;
            $path     = $file->storeAs('media/logos', $filename, 'public');

            $media = MediaFile::create([
                'file_name'   => $filename,
                'file_path'   => $path,
                'file_url'    => Storage::disk('public')->url($path),
                'mime_type'   => $file->getMimeType(),
                'size'        => $file->getSize(),
                'uploaded_by' => auth('admin')->id(),
            ]);
            $validated['logo_id'] = $media->id;
        }

        unset($validated['logo'], $validated['remove_logo']);

        $manufacturer->update($validated);

        return redirect()->route('admin.catalog.manufacturers.index')
            ->with('success', __('Manufacturer updated successfully.'));
    }

    /**
     * Remove the specified manufacturer from storage.
     */
    public function destroy(Manufacturer $manufacturer)
    {
        if ($manufacturer->products()->exists()) {
            return redirect()->route('admin.catalog.manufacturers.index')
                ->with('error', __('Cannot delete manufacturer because it has associated products.'));
        }

        $manufacturer->delete();

        return redirect()->route('admin.catalog.manufacturers.index')
            ->with('success', __('Manufacturer deleted successfully.'));
    }
}
