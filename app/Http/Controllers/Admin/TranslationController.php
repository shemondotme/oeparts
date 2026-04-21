<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\LanguageString;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TranslationController extends Controller
{
    /**
     * Display translation management dashboard.
     */
    public function index()
    {
        $languages = Language::where('is_active', true)->orderBy('sort_order')->get();
        $groupNames = LanguageString::distinct()->pluck('group');
        $totalStrings = LanguageString::count();
        $languagesCount = $languages->count();
        $groupsCount = $groupNames->count();
        $untranslatedCount = LanguageString::where(function ($query) {
            $query->where('value', '')->orWhereNull('value');
        })->count();
        $recentTranslations = LanguageString::orderBy('updated_at', 'desc')->limit(10)->get();

        // Build groups data with statistics
        $groups = [];
        foreach ($groupNames as $groupName) {
            $keysCount = LanguageString::where('group', $groupName)->distinct()->count('key');
            $translatedCount = LanguageString::where('group', $groupName)
                ->where('value', '!=', '')
                ->whereNotNull('value')
                ->count();
            $lastUpdated = LanguageString::where('group', $groupName)
                ->orderBy('updated_at', 'desc')
                ->value('updated_at');

            $groups[] = (object) [
                'group'            => $groupName,
                'description'      => null,
                'keys_count'       => $keysCount,
                'languages_count'  => $languagesCount,
                'translated_count' => $translatedCount,
                'updated_at'       => $lastUpdated ? \Carbon\Carbon::parse($lastUpdated) : null,
            ];
        }

        // Get translation progress per language
        $progress     = [];
        $languageStats = [];
        foreach ($languages as $language) {
            $total      = LanguageString::where('lang_code', $language->code)->count();
            $translated = LanguageString::where('lang_code', $language->code)
                ->where('value', '!=', '')
                ->whereNotNull('value')
                ->count();

            $progress[$language->code] = [
                'total'      => $total,
                'translated' => $translated,
                'percentage' => $total > 0 ? round(($translated / $total) * 100) : 0,
            ];

            $languageStats[] = (object) [
                'name'             => $language->name,
                'translated_count' => $translated,
            ];
        }

        return view('admin.translations.index', [
            'languages'          => $languages,
            'groups'             => $groups,
            'totalStrings'       => $totalStrings,
            'languagesCount'     => $languagesCount,
            'groupsCount'        => $groupsCount,
            'untranslatedCount'  => $untranslatedCount,
            'recentTranslations' => $recentTranslations,
            'progress'           => $progress,
            'languageStats'      => collect($languageStats),
        ]);
    }

    /**
     * Display strings for a specific group and language.
     */
    public function group(Request $request, string $group)
    {
        $language = $request->input('lang', 'en');
        $search   = $request->input('search');

        $query = LanguageString::where('group', $group);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('key', 'like', "%{$search}%")
                  ->orWhere('value', 'like', "%{$search}%");
            });
        }

        $strings   = $query->orderBy('key')->get();
        $languages = Language::where('is_active', true)->orderBy('sort_order')->get();

        // Group translations by language code
        $translations = [];
        foreach ($languages as $lang) {
            $translations[$lang->code] = $strings->where('lang_code', $lang->code);
        }

        $totalKeys = $strings->unique('key')->count();

        $translatedKeys = $strings->filter(function ($item) {
            return !empty($item->value);
        })->unique('key')->count();

        $completionRate = $totalKeys ? round(($translatedKeys / $totalKeys) * 100) : 0;

        return view('admin.translations.group', [
            'group'           => $group,
            'translations'    => $translations,
            'languages'       => $languages,
            'currentLanguage' => $language,
            'search'          => $search,
            'totalKeys'       => $totalKeys,
            'completionRate'  => $completionRate,
        ]);
    }

    /**
     * Edit a specific translation string.
     */
    public function edit(string $id)
    {
        $string    = LanguageString::findOrFail($id);
        $languages = Language::where('is_active', true)->orderBy('sort_order')->get();

        // Get all translations for this key across languages
        $translations = LanguageString::where('group', $string->group)
            ->where('key', $string->key)
            ->get()
            ->keyBy('lang_code');

        return view('admin.translations.edit', [
            'string'       => $string,
            'languages'    => $languages,
            'translations' => $translations,
        ]);
    }

    /**
     * Update translation string.
     */
    public function update(Request $request, string $id)
    {
        $string = LanguageString::findOrFail($id);

        $request->validate([
            'value' => 'required|string',
        ]);

        $string->update([
            'value' => $request->input('value'),
        ]);

        Cache::forget("translations.{$string->lang_code}.{$string->group}");

        return redirect()->route('admin.translations.group', $string->group)
            ->with('success', 'Translation updated successfully.');
    }

    /**
     * Delete a translation string.
     */
    public function destroy(string $id)
    {
        $string = LanguageString::findOrFail($id);
        $group  = $string->group;
        $lang   = $string->lang_code;

        $string->delete();

        Cache::forget("translations.{$lang}.{$group}");

        return redirect()->route('admin.translations.group', $group)
            ->with('success', 'Translation deleted successfully.');
    }

    /**
     * Bulk update translations.
     * Expects: translations[lang_code][key][value]
     */
    public function bulkUpdate(Request $request, string $group)
    {
        $translations = $request->input('translations', []);

        foreach ($translations as $langCode => $items) {
            foreach ($items as $key => $data) {
                if (isset($data['value'])) {
                    LanguageString::where('lang_code', $langCode)
                        ->where('group', $group)
                        ->where('key', $key)
                        ->update(['value' => $data['value']]);
                }
            }
        }

        // Clear translation cache for all languages
        foreach (Language::where('is_active', true)->pluck('code') as $code) {
            Cache::forget("translations.{$code}.{$group}");
        }

        return redirect()->route('admin.translations.group', $group)
            ->with('success', 'Translations updated successfully.');
    }

    /**
     * Show form to create new translation string.
     */
    public function create()
    {
        $languages = Language::where('is_active', true)->orderBy('sort_order')->get();

        $existingGroupNames = LanguageString::distinct()->pluck('group')->toArray();

        // Count of keys per group for the sidebar
        $groupCounts = [];
        foreach ($existingGroupNames as $g) {
            $groupCounts[$g] = LanguageString::where('group', $g)->distinct()->count('key');
        }

        return view('admin.translations.create', [
            'languages'      => $languages,
            'existingGroups' => $existingGroupNames,
            'groupCounts'    => $groupCounts,
        ]);
    }

    /**
     * Store new translation string.
     * translations format: ['en' => 'value', 'de' => 'value', ...]
     */
    public function store(Request $request)
    {
        $request->validate([
            'key'              => 'required|string|max:255',
            'group'            => 'required|string|max:100',
            'description'      => 'nullable|string|max:500',
            'translations'     => 'required|array',
            'translations.en'  => 'required|string',
        ]);

        $key         = $request->input('key');
        $group       = $request->input('group');
        $translations = $request->input('translations', []);

        // Check for duplicate key within the same group
        $exists = LanguageString::where('group', $group)->where('key', $key)->exists();
        if ($exists) {
            return redirect()->back()
                ->withErrors(['key' => 'This translation key already exists for this group.'])
                ->withInput();
        }

        foreach ($translations as $langCode => $value) {
            if ($value !== null && $value !== '') {
                LanguageString::create([
                    'key'      => $key,
                    'group'    => $group,
                    'lang_code' => $langCode,
                    'value'    => $value,
                ]);
            }
        }

        // Clear translation cache
        foreach (array_keys($translations) as $langCode) {
            Cache::forget("translations.{$langCode}.{$group}");
        }

        return redirect()->route('admin.translations.group', $group)
            ->with('success', 'Translation created successfully.');
    }

    /**
     * Show import form (GET).
     */
    public function importForm()
    {
        $languages = Language::where('is_active', true)->orderBy('sort_order')->get();
        $groups    = LanguageString::distinct()->pluck('group');

        return view('admin.translations.import', [
            'languages' => $languages,
            'groups'    => $groups,
        ]);
    }

    /**
     * Import translations from file (POST).
     */
    public function import(Request $request)
    {
        $request->validate([
            'file'     => 'required|file|mimes:json,csv',
            'language' => 'required|string|exists:languages,code',
            'group'    => 'nullable|string|max:100',
        ]);

        $file     = $request->file('file');
        $language = $request->input('language');
        $group    = $request->input('group', 'general');

        if ($file->getClientOriginalExtension() === 'json') {
            $this->importJson($file, $language, $group);
        } else {
            $this->importCsv($file, $language, $group);
        }

        Cache::forget("translations.{$language}.{$group}");

        return redirect()->route('admin.translations.index')
            ->with('success', 'Translations imported successfully.');
    }

    /**
     * Export translations to file.
     */
    public function export(Request $request)
    {
        $language = $request->input('lang', 'en');
        $group    = $request->input('group', 'general');
        $format   = $request->input('format', 'json');

        $strings = LanguageString::where('lang_code', $language)
            ->where('group', $group)
            ->get();

        if ($format === 'csv') {
            return $this->exportCsv($strings, $language, $group);
        }

        return $this->exportJson($strings, $language, $group);
    }

    /**
     * Export all translations for a specific group.
     */
    public function exportGroup(Request $request, string $group)
    {
        $language = $request->input('lang', 'en');
        $format   = $request->input('format', 'json');

        $strings = LanguageString::where('lang_code', $language)
            ->where('group', $group)
            ->get();

        if ($format === 'csv') {
            return $this->exportCsv($strings, $language, $group);
        }

        return $this->exportJson($strings, $language, $group);
    }

    /**
     * Scan application files for new translation strings.
     */
    public function scan()
    {
        $strings = [];

        // Scan Blade files (glob with recursive pattern)
        $bladeFiles = array_merge(
            glob(resource_path('views/*.blade.php')) ?: [],
            glob(resource_path('views/**/*.blade.php')) ?: [],
            glob(resource_path('views/**/**/*.blade.php')) ?: []
        );

        foreach ($bladeFiles as $file) {
            $content = file_get_contents($file);
            preg_match_all('/__\([\'"]([a-zA-Z0-9_\.]+)[\'"]/', $content, $matches);
            foreach ($matches[1] as $key) {
                if (!isset($strings[$key])) {
                    $strings[$key] = '';
                }
            }
        }

        return view('admin.translations.scan', [
            'strings' => $strings,
            'count'   => count($strings),
        ]);
    }

    /**
     * Add scanned strings to database.
     */
    public function addScanned(Request $request)
    {
        $request->validate([
            'strings' => 'required|array',
            'group'   => 'required|string|max:100',
        ]);

        $strings = $request->input('strings', []);
        $group   = $request->input('group');

        foreach ($strings as $key) {
            LanguageString::firstOrCreate(
                ['key' => $key, 'group' => $group, 'lang_code' => 'en'],
                ['value' => '']
            );
        }

        return redirect()->route('admin.translations.index')
            ->with('success', 'Scanned strings added successfully.');
    }

    /**
     * Display languages management page.
     */
    public function languages()
    {
        $languages = Language::orderBy('sort_order')->get();

        return view('admin.translations.languages', [
            'languages' => $languages,
        ]);
    }

    /**
     * Update language settings.
     */
    public function updateLanguage(Request $request, int $id)
    {
        $language = Language::findOrFail($id);

        $request->validate([
            'name'        => 'required|string|max:100',
            'native_name' => 'nullable|string|max:100',
            'locale'      => 'nullable|string|max:10',
            'flag_emoji'  => 'nullable|string|max:10',
            'is_active'   => 'nullable|boolean',
            'is_default'  => 'nullable|boolean',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        $isActive  = (bool) $request->input('is_active', $language->is_active);
        $isDefault = (bool) $request->input('is_default', false);

        // Cannot deactivate the default language
        if ($language->is_default && !$isActive) {
            return redirect()->back()
                ->withErrors(['is_active' => 'The default language cannot be deactivated.'])
                ->withInput();
        }

        // Only one language can be the default
        if ($isDefault && !$language->is_default) {
            return redirect()->back()
                ->withErrors(['is_default' => 'Another language is already set as default. Remove that default first.'])
                ->withInput();
        }

        $language->update([
            'name'        => $request->input('name'),
            'native_name' => $request->input('native_name', $language->native_name),
            'locale'      => $request->input('locale', $language->locale),
            'flag_emoji'  => $request->input('flag_emoji', $language->flag_emoji),
            'is_active'   => $isActive,
            'is_default'  => $isDefault,
            'sort_order'  => $request->input('sort_order', $language->sort_order),
        ]);

        return redirect()->route('admin.translations.languages')
            ->with('success', 'Language updated successfully.');
    }

    /**
     * Add new language.
     */
    public function addLanguage(Request $request)
    {
        $request->validate([
            'code'        => 'required|string|max:10|unique:languages,code',
            'name'        => 'required|string|max:100',
            'native_name' => 'nullable|string|max:100',
            'locale'      => 'nullable|string|max:10',
            'flag_emoji'  => 'nullable|string|max:10',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        Language::create([
            'code'        => $request->input('code'),
            'name'        => $request->input('name'),
            'native_name' => $request->input('native_name', $request->input('name')),
            'locale'      => $request->input('locale', $request->input('code')),
            'flag_emoji'  => $request->input('flag_emoji', ''),
            'is_active'   => true,
            'is_default'  => false,
            'sort_order'  => $request->input('sort_order', 0),
        ]);

        return redirect()->route('admin.translations.languages')
            ->with('success', 'Language added successfully.');
    }

    // ────────────────────────────────────────────────────────────
    // Private helpers
    // ────────────────────────────────────────────────────────────

    private function importJson($file, string $language, string $group): void
    {
        $content = file_get_contents($file->getRealPath());
        $data    = json_decode($content, true);

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                LanguageString::updateOrCreate(
                    ['key' => $key, 'group' => $group, 'lang_code' => $language],
                    ['value' => $value]
                );
            }
        }
    }

    private function importCsv($file, string $language, string $group): void
    {
        $handle = fopen($file->getRealPath(), 'r');

        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            if (count($data) >= 2) {
                LanguageString::updateOrCreate(
                    ['key' => $data[0], 'group' => $group, 'lang_code' => $language],
                    ['value' => $data[1]]
                );
            }
        }

        fclose($handle);
    }

    private function exportJson($strings, string $language, string $group)
    {
        $data = [];
        foreach ($strings as $string) {
            $data[$string->key] = $string->value;
        }

        $filename = "translations_{$language}_{$group}_" . date('Y-m-d_His') . '.json';

        return response()->json($data)
            ->header('Content-Disposition', "attachment; filename={$filename}");
    }

    private function exportCsv($strings, string $language, string $group)
    {
        $filename = "translations_{$language}_{$group}_" . date('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        $callback = function () use ($strings) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Key', 'Value']);

            foreach ($strings as $string) {
                fputcsv($file, [$string->key, $string->value]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
