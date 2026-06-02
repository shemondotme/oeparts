<x-filament-panels::page>
    <div class="space-y-6">
        @php
            $sections = [
                'General & Brand' => [
                    ['General Settings', '/admin/settings/general-settings', 'Store name, logo, basic details', 'heroicon-o-cog-6-tooth'],
                    ['Appearance', '/admin/settings/appearance-settings', 'Custom colors, theme styling', 'heroicon-o-paint-brush'],
                    ['Contact Info', '/admin/settings/contact-settings', 'Store location, support email, phone', 'heroicon-o-phone'],
                    ['Announcement', '/admin/settings/announcement-settings', 'Site-wide marquee promo bar', 'heroicon-o-megaphone'],
                ],
                'Store Operations' => [
                    ['Orders Policy', '/admin/settings/orders-settings', 'Order expiry, prefix formats, status defaults', 'heroicon-o-shopping-bag'],
                    ['Cart Rules', '/admin/settings/cart-settings', 'Cart duration, timeout limits', 'heroicon-o-shopping-cart'],
                    ['Shipping Engine', '/admin/settings/shipping-settings', 'Delivery zones, flat rates, thresholds', 'heroicon-o-truck'],
                    ['Payment Gateways', '/admin/settings/payment-settings', 'Airwallex card processing, B2B bank details', 'heroicon-o-credit-card'],
                    ['Tax Configurations', '/admin/settings/tax-settings', 'EU VAT rates, company VAT verification', 'heroicon-o-calculator'],
                    ['Email Setup', '/admin/settings/email-settings', 'SMTP servers, from headers, admin alerts', 'heroicon-o-envelope'],
                ],
                'SEO & Marketing' => [
                    ['Search Engine', '/admin/settings/search-settings', 'OEM normalized search query controls', 'heroicon-o-magnifying-glass'],
                    ['SEO & Meta', '/admin/settings/seo-settings', 'Global OpenGraph, robots tags, sitemap ping', 'heroicon-o-globe-alt'],
                    ['Performance', '/admin/settings/performance-settings', 'Redis caching timeouts, optimization toggles', 'heroicon-o-cpu-chip'],
                    ['Stats Counter', '/admin/settings/stats-counter-settings', 'Fake frontend counters (parts, clients)', 'heroicon-o-presentation-chart-line'],
                ],
                'System & Security' => [
                    ['Auth & Security', '/admin/settings/auth-security-settings', 'OTP login limits, password complexity rules', 'heroicon-o-lock-closed'],
                    ['Firewall & Security', '/admin/settings/security-settings', 'Max attempts, IP bans, honeypot settings', 'heroicon-o-shield-check'],
                    ['Third-party APIs', '/admin/settings/integrations-settings', 'GTM, Google Search Console trackers', 'heroicon-o-puzzle-piece'],
                    ['Maintenance & Backups', '/admin/settings/maintenance-settings', 'Maintenance display, backup triggers', 'heroicon-o-wrench-screwdriver'],
                ],
            ];
        @endphp

        @foreach ($sections as $heading => $items)
            <div class="fi-section border border-gray-200 dark:border-gray-800 rounded-xl bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
                <div class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/20 px-6 py-4">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wider font-mono">
                        {{ $heading }}
                    </h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach ($items as $item)
                            <a href="{{ url($item[1]) }}"
                               class="flex items-start gap-4 p-4 rounded-xl border border-gray-100 dark:border-gray-800/80 hover:border-amber-500/30 dark:hover:border-amber-400/30 hover:bg-amber-50/5 dark:hover:bg-amber-950/5 no-underline group transition-all duration-200 shadow-sm hover:shadow-md">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-50 dark:bg-gray-800 group-hover:bg-amber-100 dark:group-hover:bg-amber-900/30 transition-colors">
                                    @svg($item[3], 'w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors')
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors">
                                        {{ $item[0] }}
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 leading-normal">
                                        {{ $item[2] }}
                                    </div>
                                </div>
                                <div class="flex items-center self-center opacity-0 group-hover:opacity-100 transition-opacity">
                                    <x-heroicon-o-chevron-right class="w-4 h-4 text-amber-500 dark:text-amber-400" />
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
