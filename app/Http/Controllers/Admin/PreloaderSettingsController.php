<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Http\Request;

class PreloaderSettingsController extends Controller
{
    /**
     * Show the preloader settings form.
     */
    public function show()
    {
        $enabled = (bool) settings('preloader.enabled', false);
        $pathMode = settings('preloader.path_mode', 'include');
        $pathPatterns = settings('preloader.path_patterns', []);
        
        if (is_string($pathPatterns)) {
            $pathPatterns = json_decode($pathPatterns, true) ?: [];
        }

        // Common frontend pages for selection
        $availablePages = [
            'home' => [
                'label' => 'Homepage',
                'description' => 'Show preloader on the main homepage',
                'path' => '/{lang}/',
                'icon' => 'home'
            ],
            'search' => [
                'label' => 'Parts Search',
                'description' => 'Show preloader on search results page',
                'path' => '/{lang}/parts/*',
                'icon' => 'magnifying-glass'
            ],
            'cart' => [
                'label' => 'Shopping Cart',
                'description' => 'Show preloader on the cart page',
                'path' => '/{lang}/cart',
                'icon' => 'shopping-cart'
            ],
            'checkout' => [
                'label' => 'Checkout',
                'description' => 'Show preloader during checkout process',
                'path' => '/{lang}/checkout*',
                'icon' => 'credit-card'
            ],
            'account' => [
                'label' => 'Customer Account',
                'description' => 'Show preloader on customer account pages',
                'path' => '/{lang}/account*',
                'icon' => 'user'
            ],
            'blog' => [
                'label' => 'Blog & Pages',
                'description' => 'Show preloader on blog and content pages',
                'path' => '/{lang}/blog*',
                'icon' => 'newspaper'
            ],
            'brands' => [
                'label' => 'Brands Directory',
                'description' => 'Show preloader on brands listing',
                'path' => '/{lang}/brands',
                'icon' => 'tag'
            ],
        ];

        // Get display timing
        $minDisplayMs = (int) settings('preloader.min_display_ms', 450);
        $maxDisplayMs = (int) settings('preloader.max_display_ms', 6000);

        // Get preloader text settings (multilang)
        $headlineText = settings('preloader.headline', []);
        $specLineText = settings('preloader.spec_line', []);
        $sublineText = settings('preloader.subline', []);
        $statusLineText = settings('preloader.status_line', []);

        return view('admin.settings.preloader', [
            'enabled' => $enabled,
            'pathMode' => $pathMode,
            'selectedPages' => $pathPatterns,
            'availablePages' => $availablePages,
            'minDisplayMs' => $minDisplayMs,
            'maxDisplayMs' => $maxDisplayMs,
            'headlineText' => $headlineText,
            'specLineText' => $specLineText,
            'sublineText' => $sublineText,
            'statusLineText' => $statusLineText,
        ]);
    }

    /**
     * Update preloader settings.
     */
    public function update(Request $request, SettingsService $settingsService)
    {
        $validated = $request->validate([
            'preloader_enabled' => ['boolean'],
            'preloader_path_mode' => ['required', 'string', 'in:include,exclude,all'],
            'preloader_pages' => ['nullable', 'array'],
            'preloader_pages.*' => ['string'],
            'preloader_min_display_ms' => ['required', 'integer', 'min:0', 'max:5000'],
            'preloader_max_display_ms' => ['required', 'integer', 'min:500', 'max:600000'],
            'preloader_headline' => ['nullable', 'json'],
            'preloader_spec_line' => ['nullable', 'json'],
            'preloader_subline' => ['nullable', 'json'],
            'preloader_status_line' => ['nullable', 'json'],
        ]);

        try {
            // Build patterns from selected pages
            $pagePatterns = $this->buildPatterns($validated['preloader_pages'] ?? []);

            // Update settings
            Setting::updateOrCreate(
                ['group' => 'preloader', 'key' => 'enabled'],
                ['value' => $request->has('preloader_enabled') ? '1' : '0', 'type' => 'boolean']
            );

            Setting::updateOrCreate(
                ['group' => 'preloader', 'key' => 'path_mode'],
                ['value' => $validated['preloader_path_mode'], 'type' => 'string']
            );

            Setting::updateOrCreate(
                ['group' => 'preloader', 'key' => 'path_patterns'],
                ['value' => json_encode($pagePatterns), 'type' => 'json']
            );

            Setting::updateOrCreate(
                ['group' => 'preloader', 'key' => 'min_display_ms'],
                ['value' => $validated['preloader_min_display_ms'], 'type' => 'integer']
            );

            Setting::updateOrCreate(
                ['group' => 'preloader', 'key' => 'max_display_ms'],
                ['value' => $validated['preloader_max_display_ms'], 'type' => 'integer']
            );

            // Update multilang text fields if provided
            if (!empty($validated['preloader_headline'])) {
                Setting::updateOrCreate(
                    ['group' => 'preloader', 'key' => 'headline'],
                    ['value' => $validated['preloader_headline'], 'type' => 'json']
                );
            }

            if (!empty($validated['preloader_spec_line'])) {
                Setting::updateOrCreate(
                    ['group' => 'preloader', 'key' => 'spec_line'],
                    ['value' => $validated['preloader_spec_line'], 'type' => 'json']
                );
            }

            if (!empty($validated['preloader_subline'])) {
                Setting::updateOrCreate(
                    ['group' => 'preloader', 'key' => 'subline'],
                    ['value' => $validated['preloader_subline'], 'type' => 'json']
                );
            }

            if (!empty($validated['preloader_status_line'])) {
                Setting::updateOrCreate(
                    ['group' => 'preloader', 'key' => 'status_line'],
                    ['value' => $validated['preloader_status_line'], 'type' => 'json']
                );
            }

            // Clear settings cache
            $settingsService->forget('preloader');

            return redirect()->route('admin.settings.preloader')
                ->with('success', __('Preloader settings updated successfully.'));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Preloader settings update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('admin.settings.preloader')
                ->with('error', __('Failed to update preloader settings. Please try again.'));
        }
    }

    /**
     * Build path patterns from selected pages.
     *
     * @param array<string> $selectedPages
     * @return array<string>
     */
    private function buildPatterns(array $selectedPages): array
    {
        if (empty($selectedPages)) {
            return [];
        }

        $pageMapping = [
            'home' => '{lang}',
            'search' => '{lang}/parts*',
            'cart' => '{lang}/cart',
            'checkout' => '{lang}/checkout*',
            'account' => '{lang}/account*',
            'blog' => '{lang}/blog*',
            'brands' => '{lang}/brands',
        ];

        $patterns = [];
        foreach ($selectedPages as $page) {
            if (isset($pageMapping[$page])) {
                $patterns[] = $pageMapping[$page];
            }
        }

        return $patterns;
    }
}
