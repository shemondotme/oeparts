<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Enums\SettingType;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    /**
     * Display a listing of all settings groups.
     */
    public function index()
    {
        $groups = Setting::select('group')
            ->distinct()
            ->orderBy('group')
            ->pluck('group');

        $groupData = [];
        foreach ($groups as $group) {
            $groupData[$group] = Setting::where('group', $group)
                ->orderBy('key')
                ->get();
        }

        return view('admin.settings.index', [
            'groups' => $groupData,
            'settingTypes' => SettingType::cases(),
        ]);
    }

    /**
     * Show the form for editing a specific settings group.
     */
    public function edit(string $group)
    {
        $settings = Setting::where('group', $group)
            ->orderBy('key')
            ->get();

        if ($settings->isEmpty()) {
            abort(404, "Settings group '{$group}' not found.");
        }

        return view('admin.settings.edit', [
            'group' => $group,
            'settings' => $settings,
            'settingTypes' => SettingType::cases(),
        ]);
    }

    /**
     * Update the specified settings group.
     */
    public function update(Request $request, string $group, SettingsService $settingsService)
    {
        $settings = Setting::where('group', $group)->get();

        if ($settings->isEmpty()) {
            abort(404, "Settings group '{$group}' not found.");
        }

        $rules = [];
        $customMessages = [];

        foreach ($settings as $setting) {
            $key = "settings.{$setting->key}";
            
            // Build validation rules based on setting type
            switch ($setting->type) {
                case SettingType::Boolean:
                    $rules[$key] = ['nullable', 'boolean'];
                    break;
                case SettingType::Integer:
                    $rules[$key] = ['nullable', 'integer'];
                    break;
                case SettingType::Decimal:
                    $rules[$key] = ['nullable', 'numeric'];
                    break;
                case SettingType::Json:
                    $rules[$key] = ['nullable', 'json'];
                    break;
                case SettingType::Encrypted:
                    $rules[$key] = ['nullable', 'string'];
                    break;
                default: // String
                    $rules[$key] = ['nullable', 'string', 'max:65535'];
                    break;
            }

            // Add custom attribute names for better error messages
            $customMessages["{$key}.required"] = "The {$setting->key} field is required.";
        }

        $validator = Validator::make($request->all(), $rules, $customMessages);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();
        try {
            foreach ($settings as $setting) {
                $key = "settings.{$setting->key}";
                $value = $request->input($key);

                // Handle empty values
                if ($value === null || $value === '') {
                    $value = $setting->type === SettingType::Boolean ? '0' : '';
                }

                // Convert boolean to string
                if ($setting->type === SettingType::Boolean) {
                    $value = $value ? '1' : '0';
                }

                // Encrypt value if needed
                if ($setting->type === SettingType::Encrypted || $setting->is_encrypted) {
                    $value = 'base64:' . base64_encode($value);
                }

                $setting->update(['value' => $value]);
            }

            // Clear cache for this group
            $settingsService->forget($group);

            DB::commit();

            return redirect()->route('admin.settings.edit', $group)
                ->with('success', __('Settings updated successfully.'));
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Settings update failed', [
                'group' => $group,
                'error' => $e->getMessage(),
            ]);
            return redirect()->back()
                ->with('error', __('Failed to update settings. Please try again.'))
                ->withInput();
        }
    }

    /**
     * Show the form for creating a new setting.
     */
    public function create()
    {
        $existingGroups = Setting::select('group')
            ->distinct()
            ->orderBy('group')
            ->pluck('group')
            ->toArray();

        return view('admin.settings.create', [
            'existingGroups' => $existingGroups,
            'settingTypes' => SettingType::cases(),
        ]);
    }

    /**
     * Store a newly created setting.
     */
    public function store(Request $request)
    {
        // Base validation rules
        $rules = [
            'group' => ['required', 'string', 'max:100'],
            'key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'type' => ['required', Rule::enum(SettingType::class)],
            'is_encrypted' => ['nullable', 'boolean'],
        ];

        // Get type from request to apply type-specific validation
        $type = $request->input('type');
        
        // Add type-specific validation for value
        switch ($type) {
            case SettingType::Boolean->value:
                $rules['value'] = ['nullable', 'boolean'];
                break;
            case SettingType::Integer->value:
                $rules['value'] = ['nullable', 'integer'];
                break;
            case SettingType::Decimal->value:
                $rules['value'] = ['nullable', 'numeric'];
                break;
            case SettingType::Json->value:
                $rules['value'] = ['nullable', 'json'];
                break;
            case SettingType::Encrypted->value:
                $rules['value'] = ['nullable', 'string'];
                break;
            default: // String
                $rules['value'] = ['nullable', 'string', 'max:65535'];
                break;
        }

        $validated = $request->validate($rules);

        // Check if setting already exists
        $exists = Setting::where('group', $validated['group'])
            ->where('key', $validated['key'])
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->with('error', __('A setting with this group and key already exists.'))
                ->withInput();
        }

        // Encrypt value if needed
        $value = $validated['value'] ?? '';
        if ($validated['type'] === SettingType::Encrypted->value || ($validated['is_encrypted'] ?? false)) {
            $value = 'base64:' . base64_encode($value);
        }

        Setting::create([
            'group' => $validated['group'],
            'key' => $validated['key'],
            'value' => $value,
            'type' => $validated['type'],
            'is_encrypted' => $validated['is_encrypted'] ?? false,
        ]);

        return redirect()->route('admin.settings.edit', $validated['group'])
            ->with('success', __('Setting created successfully.'));
    }

    /**
     * Remove the specified setting.
     */
    public function destroy(string $group, string $key)
    {
        $setting = Setting::where('group', $group)
            ->where('key', $key)
            ->firstOrFail();

        // Prevent deletion of required settings
        $requiredSettings = $this->getRequiredSettings();
        if (in_array("{$group}.{$key}", $requiredSettings)) {
            return redirect()->back()
                ->with('error', __('This setting is required and cannot be deleted.'));
        }

        $setting->delete();

        return redirect()->route('admin.settings.edit', $group)
            ->with('success', __('Setting deleted successfully.'));
    }

    /**
     * Get list of required settings that cannot be deleted.
     */
    private function getRequiredSettings(): array
    {
        return [
            'general.site_name',
            'general.site_url',
            'general.default_locale',
            'general.currency',
            'tax.default_vat_rate',
            'auth.otp_length',
            'auth.otp_expiry_minutes',
            'email.from_name',
            'email.from_address',
        ];
    }
}