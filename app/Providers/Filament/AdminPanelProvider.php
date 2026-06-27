<?php

namespace App\Providers\Filament;

use App\Filament\Clusters\Reports;
use App\Filament\Clusters\Settings;
use App\Filament\Pages\Dashboard;
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
use Illuminate\Support\Facades\View;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use pxlrbt\FilamentSpotlight\SpotlightPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->authGuard('admin')
            ->login(\App\Filament\Pages\Auth\CustomLogin::class)
            ->simplePageMaxContentWidth(Width::Medium)
            ->colors([
                'primary' => Color::hex('#0A1228'),
                'warning' => Color::hex('#F59E0B'),
                'success' => Color::hex('#10B981'),
                'danger'  => Color::hex('#EF4444'),
                'info'    => Color::hex('#0A1228'),
                'gray'    => Color::Stone,
            ])
            ->font('Geist Sans')
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => Blade::render('<x-admin.skip-nav /><x-admin.aria-enhancer />'),
            )
            ->renderHook(
                PanelsRenderHook::SIMPLE_LAYOUT_START,
                fn (): string => view('filament.login-banner')->render(),
            )
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): string => Blade::render("@vite('resources/css/filament/admin/theme.css')"),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render("@vite('resources/js/filament/admin/dashboard-canvas.js')"),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                // Login.js only on unauthenticated (simple-layout) pages
                fn (): string => ! filament()->auth()->check()
                    ? Blade::render("@vite('resources/js/filament/admin/login.js')")
                    : '',
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): string => filament()->auth()->check()
                    ? Blade::render('@livewire(\'notification-center\')')
                    : '',
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): string => filament()->auth()->check()
                    ? Blade::render('@livewire(\'health-indicator\')')
                    : '',
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => filament()->auth()->check()
                    ? Blade::render('@livewire(\'jump-to-oem\')')
                    : '',
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn (): string => filament()->auth()->check()
                    ? Blade::render('@livewire(\'sidebar-pinned-nav\')')
                    : '',
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_END,
                fn (): string => filament()->auth()->check()
                    ? Blade::render('@livewire(\'sidebar-recent-nav\')')
                    : '',
            )
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn (): string => Blade::render('<x-admin.loading-bar /><x-admin.toast /><x-admin.keyboard-shortcuts />'),
            )
            ->brandName('OeParts')
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('favicon.ico'))
            ->unsavedChangesAlerts()
            ->profile(isSimple: false)
            ->sidebarWidth('3.5rem') // Rail-only width; flyout panel is position:absolute
            ->spa()
            ->darkMode()
            ->plugins([
                SpotlightPlugin::make(),
            ])
            // ->collapsible() intentionally omitted: the custom rail+panel sidebar
            // (livewire/sidebar.blade.php) ignores it entirely, using Alpine
            // activeGroup state instead. It only affects Filament's stock
            // sub-navigation sidebar, which no resource currently uses.
            ->navigationGroups([
                NavigationGroup::make('Commerce')
                    ->icon('heroicon-o-shopping-bag'),
                NavigationGroup::make('Catalog')
                    ->icon('heroicon-o-book-open'),
                NavigationGroup::make('Customers')
                    ->icon('heroicon-o-user-group'),
                NavigationGroup::make('Marketing')
                    ->icon('heroicon-o-megaphone'),
                NavigationGroup::make('Content')
                    ->icon('heroicon-o-document-text'),
                NavigationGroup::make('System')
                    ->icon('heroicon-o-server-stack'),
            ])
            ->navigationItems([])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
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
                \App\Http\Middleware\RecordsAdminPageVisit::class,
            ]);
    }
}
