<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Manufacturer;
use App\Models\CarModel;
use App\Models\BulkUpdateLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BulkUpdateController extends Controller
{
    /**
     * Show the bulk update form with filter options.
     */
    public function index(Request $request)
    {
        $entityType = $request->get('entity', 'products');
        
        return view('admin.catalog.bulk-update.index', [
            'entityType' => $entityType,
            'manufacturers' => Manufacturer::orderBy('name')->get(),
            'conditions' => \App\Enums\ProductCondition::cases(),
        ]);
    }

    /**
     * Preview the bulk update based on filters.
     */
    public function preview(Request $request)
    {
        $validated = $request->validate([
            'entity_type' => ['required', Rule::in(['products', 'manufacturers', 'car_models'])],
            'filters' => 'required|array',
            'updates' => 'required|array',
        ]);

        $entityType = $validated['entity_type'];
        $filters = $validated['filters'];
        $updates = $validated['updates'];

        // Build query based on entity type
        $query = $this->buildFilterQuery($entityType, $filters);
        
        // Get count and sample records
        $totalCount = $query->count();
        $sampleRecords = $query->limit(10)->get();

        // Validate updates
        $validationErrors = $this->validateUpdates($entityType, $updates);
        
        if (!empty($validationErrors)) {
            return response()->json([
                'success' => false,
                'errors' => $validationErrors,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'total_count' => $totalCount,
            'sample_records' => $sampleRecords,
            'updates' => $updates,
            'preview_summary' => $this->generatePreviewSummary($entityType, $updates, $totalCount),
        ]);
    }

    /**
     * Execute the bulk update.
     */
    public function execute(Request $request)
    {
        $validated = $request->validate([
            'entity_type' => ['required', Rule::in(['products', 'manufacturers', 'car_models'])],
            'filters' => 'required|array',
            'updates' => 'required|array',
            'confirmation' => 'required|string',
        ]);

        if ($validated['confirmation'] !== 'CONFIRM') {
            return response()->json([
                'success' => false,
                'message' => __('Confirmation code is incorrect.'),
            ], 422);
        }

        $entityType = $validated['entity_type'];
        $filters = $validated['filters'];
        $updates = $validated['updates'];

        // Build query
        $query = $this->buildFilterQuery($entityType, $filters);
        $records = $query->get();
        $recordIds = $records->pluck('id')->toArray();

        if (empty($recordIds)) {
            return response()->json([
                'success' => false,
                'message' => __('No records match the selected filters.'),
            ], 422);
        }

        // Start transaction
        DB::beginTransaction();

        try {
            $updatedCount = 0;
            
            switch ($entityType) {
                case 'products':
                    $updatedCount = Product::whereIn('id', $recordIds)->update($this->prepareProductUpdates($updates));
                    break;
                case 'manufacturers':
                    $updatedCount = Manufacturer::whereIn('id', $recordIds)->update($this->prepareManufacturerUpdates($updates));
                    break;
                case 'car_models':
                    $updatedCount = CarModel::whereIn('id', $recordIds)->update($this->prepareCarModelUpdates($updates));
                    break;
            }

            // Log the bulk update
            $log = BulkUpdateLog::create([
                'admin_id' => auth('admin')->id(),
                'entity_type' => $entityType,
                'filters' => $filters,
                'updates' => $updates,
                'affected_rows_count' => $updatedCount,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('Bulk update completed successfully.'),
                'updated_count' => $updatedCount,
                'log_id' => $log->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Bulk update failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => __('Bulk update failed. Please try again.'),
            ], 500);
        }
    }

    /**
     * Show bulk update logs.
     */
    public function logs(Request $request)
    {
        $query = BulkUpdateLog::with('admin')->latest();

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }

        if ($request->filled('admin_id')) {
            $query->where('admin_id', $request->admin_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(20);

        return view('admin.catalog.bulk-update.logs', compact('logs'));
    }

    /**
     * Show log details.
     */
    public function showLog(BulkUpdateLog $log)
    {
        return view('admin.catalog.bulk-update.log-detail', compact('log'));
    }

    /**
     * Build filter query based on entity type and filters.
     */
    private function buildFilterQuery(string $entityType, array $filters)
    {
        switch ($entityType) {
            case 'products':
                $query = Product::query();
                
                if (!empty($filters['oem_number'])) {
                    $query->where('oem_number', 'like', '%' . $filters['oem_number'] . '%');
                }
                
                if (!empty($filters['manufacturer_id'])) {
                    $query->where('manufacturer_id', $filters['manufacturer_id']);
                }
                
                if (isset($filters['condition']) && $filters['condition'] !== '') {
                    $query->where('condition', $filters['condition']);
                }
                
                if (isset($filters['is_active']) && $filters['is_active'] !== '') {
                    $query->where('is_active', (bool)$filters['is_active']);
                }
                
                if (isset($filters['stock_status']) && $filters['stock_status'] !== '') {
                    if ($filters['stock_status'] === 'in_stock') {
                        $query->where('is_in_stock', true);
                    } elseif ($filters['stock_status'] === 'out_of_stock') {
                        $query->where('is_in_stock', false);
                    }
                }
                
                break;

            case 'manufacturers':
                $query = Manufacturer::query();
                
                if (!empty($filters['name'])) {
                    $query->where('name->en', 'like', '%' . $filters['name'] . '%');
                }
                
                if (!empty($filters['country_code'])) {
                    $query->where('country_code', $filters['country_code']);
                }
                
                if (isset($filters['is_active']) && $filters['is_active'] !== '') {
                    $query->where('is_active', (bool)$filters['is_active']);
                }
                
                if (isset($filters['is_oem_verified']) && $filters['is_oem_verified'] !== '') {
                    $query->where('is_verified_oem', (bool)$filters['is_oem_verified']);
                }
                
                break;

            case 'car_models':
                $query = CarModel::query();
                
                if (!empty($filters['manufacturer_id'])) {
                    $query->where('manufacturer_id', $filters['manufacturer_id']);
                }
                
                if (!empty($filters['name'])) {
                    $query->where('name', 'like', '%' . $filters['name'] . '%');
                }
                
                if (isset($filters['is_active']) && $filters['is_active'] !== '') {
                    $query->where('is_active', (bool)$filters['is_active']);
                }
                
                if (!empty($filters['year_from'])) {
                    $query->where('year_from', '>=', $filters['year_from']);
                }
                
                if (!empty($filters['year_to'])) {
                    $query->where('year_to', '<=', $filters['year_to']);
                }
                
                break;

            default:
                throw new \InvalidArgumentException("Invalid entity type: {$entityType}");
        }

        return $query;
    }

    /**
     * Validate updates for the given entity type.
     */
    private function validateUpdates(string $entityType, array $updates): array
    {
        $rules = [];
        
        switch ($entityType) {
            case 'products':
                $rules = [
                    'price' => 'nullable|numeric|min:0',
                    'is_in_stock' => 'nullable|boolean',
                    'is_active' => 'nullable|boolean',
                    'condition' => ['nullable', Rule::in(array_column(\App\Enums\ProductCondition::cases(), 'value'))],
                ];
                break;

            case 'manufacturers':
                $rules = [
                    'is_active' => 'nullable|boolean',
                    'is_verified_oem' => 'nullable|boolean',
                    'country_code' => 'nullable|string|size:2',
                ];
                break;
                
            case 'car_models':
                $rules = [
                    'is_active' => 'nullable|boolean',
                    'year_from' => 'nullable|integer|min:1900|max:2100',
                    'year_to' => 'nullable|integer|min:1900|max:2100',
                ];
                break;
        }

        $validator = Validator::make($updates, $rules);
        
        return $validator->errors()->toArray();
    }

    /**
     * Prepare product updates for mass assignment.
     */
    private function prepareProductUpdates(array $updates): array
    {
        $prepared = [];
        
        if (isset($updates['price'])) {
            $prepared['price'] = $updates['price'];
        }

        if (isset($updates['is_in_stock'])) {
            $prepared['is_in_stock'] = (bool)$updates['is_in_stock'];
        }

        if (isset($updates['is_active'])) {
            $prepared['is_active'] = (bool)$updates['is_active'];
        }
        
        if (isset($updates['condition'])) {
            $prepared['condition'] = $updates['condition'];
        }
        
        return $prepared;
    }

    /**
     * Prepare manufacturer updates for mass assignment.
     */
    private function prepareManufacturerUpdates(array $updates): array
    {
        $prepared = [];
        
        if (isset($updates['is_active'])) {
            $prepared['is_active'] = (bool)$updates['is_active'];
        }
        
        if (isset($updates['is_verified_oem'])) {
            $prepared['is_verified_oem'] = (bool)$updates['is_verified_oem'];
        }
        
        if (isset($updates['country_code'])) {
            $prepared['country_code'] = $updates['country_code'];
        }
        
        return $prepared;
    }

    /**
     * Prepare car model updates for mass assignment.
     */
    private function prepareCarModelUpdates(array $updates): array
    {
        $prepared = [];
        
        if (isset($updates['is_active'])) {
            $prepared['is_active'] = (bool)$updates['is_active'];
        }
        
        if (isset($updates['year_from'])) {
            $prepared['year_from'] = $updates['year_from'];
        }
        
        if (isset($updates['year_to'])) {
            $prepared['year_to'] = $updates['year_to'];
        }
        
        return $prepared;
    }

    /**
     * Generate a human-readable preview summary.
     */
    private function generatePreviewSummary(string $entityType, array $updates, int $count): string
    {
        $summaryParts = [];
        
        foreach ($updates as $field => $value) {
            if ($value !== null && $value !== '') {
                $summaryParts[] = "{$field} → " . (is_bool($value) ? ($value ? 'Yes' : 'No') : $value);
            }
        }
        
        $entityName = match($entityType) {
            'products' => 'products',
            'manufacturers' => 'manufacturers',
            'car_models' => 'car models',
            default => 'records',
        };
        
        return "Update {$count} {$entityName}: " . implode(', ', $summaryParts);
    }
}