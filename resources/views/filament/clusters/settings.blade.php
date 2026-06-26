@include('filament.components.admin-styles')
<x-filament-panels::page>
    <div class="space-y-6" x-data="{ search: '' }">
        {{-- Settings Header --}}
        <div class="op-card relative overflow-hidden p-6 page-header-gradient page-header-border">
            <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold tracking-tight flex items-center gap-2" style="font-family: var(--font-display); color: var(--color-text-on-accent);">
                        <x-heroicon-o-adjustments-horizontal class="w-5 h-5" style="color: var(--color-warning-500);" />
                        System Settings Engine
                    </h2>
                    <p class="mt-1 text-sm max-w-2xl leading-relaxed" style="color: var(--color-text-muted);">
                        Configure your B2B/B2C auto parts platform. Settings apply in real-time across storefront routing, pricing, and API integrations.
                    </p>
                </div>
                <div class="flex items-center gap-2 text-xs font-mono px-3 py-1.5 rounded-lg shrink-0 w-fit"
                    style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: var(--color-success-400);">
                    <span class="h-2 w-2 rounded-full animate-pulse" style="background: var(--color-success-500);"></span>
                    ACTIVE
                </div>
            </div>
        </div>

        @include('filament.components.cluster-search', ['placeholder' => 'Search settings... (e.g. SMTP, payment, cache)'])

        @php
            $sections = [
                'General & Brand' => [
                    'icon' => 'heroicon-o-identification',
                    'keywords' => 'general brand appearance contact announcement logo name theme',
                    'items' => [
                        ['General Settings', '/admin/settings/general-settings', 'Store name, logo, basic details', 'heroicon-o-cog-6-tooth'],
                        ['Appearance', '/admin/settings/appearance-settings', 'Custom colors, theme styling', 'heroicon-o-paint-brush'],
                        ['Contact Info', '/admin/settings/contact-settings', 'Store location, support email, phone', 'heroicon-o-phone'],
                        ['Announcement', '/admin/settings/announcement-settings', 'Site-wide marquee promo bar', 'heroicon-o-megaphone'],
                    ]
                ],
                'Store Operations' => [
                    'icon' => 'heroicon-o-cog',
                    'keywords' => 'orders cart shipping payment tax email smtp bank transfer airwallex company store checkout',
                    'items' => [
                        ['Company Info', '/admin/settings/company-settings', 'Company details for invoices, legal, and emails', 'heroicon-o-building-office-2'],
                        ['Store Currency', '/admin/settings/store-settings', 'Currency, symbol, locale formatting', 'heroicon-o-banknotes'],
                        ['Orders Policy', '/admin/settings/orders-settings', 'Order expiry, prefix formats, status defaults', 'heroicon-o-shopping-bag'],
                        ['Cart Rules', '/admin/settings/cart-settings', 'Cart duration, timeout limits', 'heroicon-o-shopping-cart'],
                        ['Shipping Engine', '/admin/settings/shipping-settings', 'Delivery zones, flat rates, thresholds', 'heroicon-o-truck'],
                        ['Payment Gateways', '/admin/settings/payment-settings', 'Airwallex card processing, B2B bank details', 'heroicon-o-credit-card'],
                        ['Tax Configurations', '/admin/settings/tax-settings', 'EU VAT rates, company VAT verification', 'heroicon-o-calculator'],
                        ['Email Setup', '/admin/settings/email-settings', 'SMTP servers, from headers, admin alerts', 'heroicon-o-envelope'],
                    ]
                ],
                'SEO & Marketing' => [
                    'icon' => 'heroicon-o-globe-alt',
                    'keywords' => 'seo search engine performance cache redis stats counter meta opengraph preloader newsletter sections ui hero homepage',
                    'items' => [
                        ['Search Engine', '/admin/settings/search-settings', 'OEM normalized search query controls', 'heroicon-o-magnifying-glass'],
                        ['SEO & Meta', '/admin/settings/seo-settings', 'Global OpenGraph, robots tags, sitemap ping', 'heroicon-o-globe-alt'],
                        ['Performance', '/admin/settings/performance-settings', 'Redis caching timeouts, optimization toggles', 'heroicon-o-cpu-chip'],
                        ['Stats Counter', '/admin/settings/stats-counter-settings', 'Fake frontend counters (parts, clients)', 'heroicon-o-presentation-chart-line'],
                        ['Preloader', '/admin/settings/preloader-settings', 'Full-screen loading animation settings', 'heroicon-o-arrow-path'],
                        ['Newsletter', '/admin/settings/newsletter-settings', 'Subscription rate limits and opt-in settings', 'heroicon-o-envelope'],
                        ['Sections', '/admin/settings/sections-settings', 'Homepage content section display limits', 'heroicon-o-squares-2x2'],
                        ['Homepage Hero & UI Text', '/admin/settings/ui-settings', 'Hero banner, spec table, and footer pill copy', 'heroicon-o-paint-brush'],
                    ]
                ],
                'System & Security' => [
                    'icon' => 'heroicon-o-shield-check',
                    'keywords' => 'auth security firewall ip ban otp password maintenance backup api integration gtm checkout inquiry activity log audit',
                    'items' => [
                        ['Auth & Security', '/admin/settings/auth-security-settings', 'OTP login limits, password complexity rules', 'heroicon-o-lock-closed'],
                        ['Firewall & Security', '/admin/settings/security-settings', 'Max attempts, IP bans, honeypot settings', 'heroicon-o-shield-check'],
                        ['Checkout', '/admin/settings/checkout-settings', 'Payment methods, timeouts, customer messages', 'heroicon-o-credit-card'],
                        ['Part Inquiry', '/admin/settings/part-inquiry-settings', 'Response time SLA, guest inquiry limits', 'heroicon-o-chat-bubble-left-ellipsis'],
                        ['Third-party APIs', '/admin/settings/integrations-settings', 'GTM, Google Search Console trackers', 'heroicon-o-puzzle-piece'],
                        ['Maintenance & Backups', '/admin/settings/maintenance-settings', 'Maintenance display, backup triggers', 'heroicon-o-wrench-screwdriver'],
                        ['Settings Activity Log', '/admin/settings/settings-activity-log', 'Track who changed settings and when', 'heroicon-o-clock'],
                    ]
                ],
                'Menus & Social' => [
                    'icon' => 'heroicon-o-globe-alt',
                    'keywords' => 'menu navigation footer social facebook instagram twitter links',
                    'items' => [
                        ['Menu Settings', '/admin/settings/menu-settings', 'Configure storefront header and footer navigation', 'heroicon-o-bars-3'],
                        ['Social Links', '/admin/settings/social-link-settings', 'Social media profile URLs for footer and sharing', 'heroicon-o-globe-alt'],
                    ]
                ],
                'About & Info' => [
                    'icon' => 'heroicon-o-information-circle',
                    'keywords' => 'about license version database info system',
                    'items' => [
                        ['About & License', '/admin/settings/about-license-settings', 'Platform version, PHP/MySQL info, MIT license', 'heroicon-o-information-circle'],
                        ['Database Info', '/admin/settings/database-settings', 'Connection status, table summary, maintenance', 'heroicon-o-server-stack'],
                    ]
                ],
            ];
        @endphp

        @foreach ($sections as $heading => $sectionData)
            <div
                x-show="search === '' || '{{ strtolower($heading) . ' ' . $sectionData['keywords'] }}'.includes(search.toLowerCase()) || {{ collect($sectionData['items'])->map(fn($i) => strtolower($i[0] . ' ' . $i[2]))->implode(' ') }}.includes(search.toLowerCase())"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="op-card overflow-hidden"
                style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);"
            >
                {{-- Group Header --}}
                <div class="px-6 py-4 flex items-center gap-3" style="border-bottom: 1px solid var(--color-border-subtle); background: var(--color-bg-inset);">
                    <div class="p-1.5 rounded-lg" style="background: var(--color-bg-surface); color: var(--color-text-muted);">
                        @svg($sectionData['icon'], 'w-4 h-4')
                    </div>
                    <h3 class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">
                        {{ $heading }}
                    </h3>
                </div>
                {{-- Group Content --}}
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach ($sectionData['items'] as $item)
                            <a href="{{ url($item[1]) }}"
                               x-show="search === '' || '{{ strtolower($item[0] . ' ' . $item[2]) }}'.includes(search.toLowerCase())"
                               class="settings-link op-focus-ring op-press flex items-start gap-4 p-4 rounded-md transition-all duration-200 no-underline group"
                               style="border: 1px solid var(--color-border-subtle); background: var(--color-bg-inset);"
                            >
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg transition-all duration-200 group-hover:scale-110"
                                    style="background: var(--color-bg-surface); border: 1px solid var(--color-border-default);">
                                    @svg($item[3], 'w-5 h-5 transition-colors duration-200', ['style' => 'color: var(--color-warning-600);'])
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold transition-colors duration-200" style="color: var(--color-text-primary); font-family: var(--font-display);">
                                        {{ $item[0] }}
                                    </div>
                                    <div class="mt-1 text-xs leading-normal" style="color: var(--color-text-muted);">
                                        {{ $item[2] }}
                                    </div>
                                </div>
                                <div class="flex items-center self-center opacity-0 group-hover:opacity-100 translate-x-[-4px] group-hover:translate-x-0 transition-all duration-300">
                                    <x-heroicon-o-arrow-right class="w-4 h-4" style="color: var(--color-accent-500);" />
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach

        {{-- No Results --}}
        @php
            $allItemKeywords = collect($sections)->flatMap(function ($section) {
                return collect($section['items'])->map(fn($item) => strtolower($item[0] . ' ' . $item[2]));
            })->values()->implode(' ');
            $sectionKeywords = collect($sections)->map(fn($s, $k) => strtolower($k . ' ' . $s['keywords']))->values()->implode(' ');
        @endphp
        <div x-show="search.length > 0 && !'{{ $sectionKeywords }}'.includes(search.toLowerCase()) && !'{{ $allItemKeywords }}'.includes(search.toLowerCase())"
            class="op-card p-8 text-center" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <p class="text-sm font-medium" style="color: var(--color-text-muted);">No settings match your search.</p>
        </div>
    </div>
</x-filament-panels::page>
