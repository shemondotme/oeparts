<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\System\HelpPage;
use App\Filament\Pages\System\ServerMonitor;
use Filament\Navigation\MenuItem;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use App\Http\Middleware\IpBlocklist;
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
            ->login(\App\Filament\Pages\Auth\CustomLogin::class)
            ->simplePageMaxContentWidth(Width::Medium)
            ->colors([
                'primary' => Color::Indigo,
                'gray'    => Color::Zinc,
                'danger'  => Color::Rose,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'info'    => Color::Blue,
            ])
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): string => Blade::render("@vite('resources/css/filament/admin/theme.css')"),
            )
            ->brandName('OeParts')
            ->brandLogo(fn () => view('filament.brand-logo'))
            ->brandLogoHeight('1.9rem')
            ->favicon(asset('favicon.ico'))
            ->unsavedChangesAlerts()
            ->profile(isSimple: false)
            ->spa()
            ->darkMode()
            // Let power users collapse the sidebar to icons for more workspace.
            // No group icons are set (rule #36) — item icons are kept, which is
            // what the collapsed rail shows.
            ->sidebarCollapsibleOnDesktop()
            // Native bell icon + notifications panel in the topbar. Fed by
            // App\Support\AdminNotifier from observers (new orders, refunds…).
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            // Topbar user-menu quick links (account/profile already added by
            // ->profile()). Storefront opens the live site in a new tab.
            ->userMenuItems([
                MenuItem::make()
                    ->label('View storefront')
                    // Storefront routes are language-prefixed ({lang}); pass a
                    // locale so URL generation doesn't throw.
                    ->url(fn (): string => route('frontend.home', ['lang' => app()->getLocale()]), shouldOpenInNewTab: true)
                    ->icon('heroicon-o-arrow-top-right-on-square'),
                MenuItem::make()
                    ->label('Help & docs')
                    ->url(fn (): string => HelpPage::getUrl())
                    ->icon('heroicon-o-question-mark-circle'),
                MenuItem::make()
                    ->label('System status')
                    ->url(fn (): string => ServerMonitor::getUrl())
                    ->icon('heroicon-o-server-stack')
                    ->visible(fn (): bool => auth('admin')->user()?->hasAnyRole(['super_admin', 'admin']) ?? false),
            ])
            // Topbar layout (native components via render hooks):
            //   [logo] [env badge] … [search] [+ New] [bell] [avatar]
            // Env badge sits right after the brand logo; the quick-create
            // button sits right after global search, before the bell.
            ->renderHook(
                PanelsRenderHook::TOPBAR_LOGO_AFTER,
                fn (): string => Blade::render('@include(\'filament.topbar.env-badge\')'),
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => Blade::render('@include(\'filament.topbar.quick-create\')'),
            )
            // "Update available" admin notice at the top of every page — reads only the
            // cached UpdateChecker status (no network) and is gated on `view updates`
            // inside the partial (Update System, Module 21).
            ->renderHook(
                PanelsRenderHook::PAGE_START,
                fn (): string => Blade::render('@include(\'filament.hooks.update-banner\')'),
            )
            ->navigationGroups([
                NavigationGroup::make('Commerce'),
                NavigationGroup::make('Catalog'),
                NavigationGroup::make('Customers'),
                NavigationGroup::make('Marketing'),
                // Administration (config/logs/SEO) starts collapsed to declutter
                // the dense sidebar; the daily-work groups above stay expanded.
                // (The old 'CMS' group was folded into the Content cluster.)
                NavigationGroup::make('Administration')->collapsed(),
            ])
            // Widget Preferences moved from here to a "Customize" button in the
            // Dashboard page header (App\Filament\Pages\Dashboard) for
            // discoverability.
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->middleware([
                IpBlocklist::class,
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
