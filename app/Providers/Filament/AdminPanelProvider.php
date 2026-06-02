<?php

namespace App\Providers\Filament;

use App\Filament\Clusters\Reports;
use App\Filament\Clusters\Settings;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\CheckoutDropoffChart;
use App\Filament\Widgets\CustomerGrowthChart;
use App\Filament\Widgets\DashboardAlerts;
use App\Filament\Widgets\DashboardKpiStats;
use App\Filament\Widgets\FailedSearchesWidget;
use App\Filament\Widgets\HealthStrip;
use App\Filament\Widgets\OrderStatusDistribution;
use App\Filament\Widgets\PaymentMethodSplit;
use App\Filament\Widgets\RecentActivityLog;
use App\Filament\Widgets\RecentOrdersList;
use App\Filament\Widgets\RevenueChart;
use App\Filament\Widgets\SalesByCountryChart;
use App\Filament\Widgets\TopManufacturersRevenue;
use App\Filament\Widgets\TopSearchedOems;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->authGuard('admin')
            ->login()
            ->simplePageMaxContentWidth(Width::Medium)
            ->colors([
                'primary' => Color::hex('#0B3A68'),
                'warning' => Color::hex('#F59E0B'),
                'success' => Color::hex('#10B981'),
                'danger'  => Color::hex('#EF4444'),
                'info'    => Color::hex('#3B82F6'),
                'gray'    => Color::hex('#64748B'),
            ])
            ->font('Inter')
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): string => Blade::render("@vite('resources/css/filament/admin/theme.css')"),
            )
            ->brandName('OeParts')
            ->brandLogo(fn () => view('filament.brand'))
            ->favicon(asset('favicon.ico'))
            ->darkMode(true)
            ->profile(isSimple: false)
            ->sidebarCollapsibleOnDesktop()
            ->spa()
            ->navigationGroups([
                NavigationGroup::make('Commerce')
                    ->icon('heroicon-o-shopping-bag')
                    ->collapsible(),
                NavigationGroup::make('Catalog')
                    ->icon('heroicon-o-book-open')
                    ->collapsible(),
                NavigationGroup::make('Customers')
                    ->icon('heroicon-o-user-group')
                    ->collapsible(),
                NavigationGroup::make('Marketing')
                    ->icon('heroicon-o-megaphone')
                    ->collapsible(),
                NavigationGroup::make('Content')
                    ->icon('heroicon-o-document-text')
                    ->collapsible(),
                NavigationGroup::make('System')
                    ->icon('heroicon-o-server-stack')
                    ->collapsible(),
            ])
            ->navigationItems([])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                DashboardKpiStats::class,
                DashboardAlerts::class,
                RevenueChart::class,
                RecentOrdersList::class,
                TopSearchedOems::class,
                TopManufacturersRevenue::class,
                FailedSearchesWidget::class,
                CheckoutDropoffChart::class,
                CustomerGrowthChart::class,
                SalesByCountryChart::class,
                OrderStatusDistribution::class,
                PaymentMethodSplit::class,
                RecentActivityLog::class,
                HealthStrip::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
