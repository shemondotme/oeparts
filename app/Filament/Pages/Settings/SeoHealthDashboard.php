<?php

namespace App\Filament\Pages\Settings;

use Filament\Actions\Action;
use Filament\Pages\Page;

/**
 * Advanced SEO analytics/health surface, kept separate from
 * SeoControlCenter's settings-form lifecycle — Filament's own idiomatic
 * building block for live/polling stats is a plain widget-composed Page,
 * not a form page. Reached only via the Control Center's header action
 * (never in the sidebar nav, and not part of SettingsRegistry — it holds
 * no settings of its own, so SettingsRegistryTest's one-entry-per-page
 * rule does not apply to it).
 */
class SeoHealthDashboard extends Page
{
    protected string $view = 'filament.pages.settings.seo-health-dashboard';

    protected static ?string $title = 'SEO Health Dashboard';

    protected static ?string $slug = 'seo-settings/health';

    protected static bool $shouldRegisterNavigation = false;

    /** Mirrors SettingsPage::canAccess() — this page sits alongside SeoControlCenter but doesn't extend it. */
    public static function canAccess(): bool
    {
        return auth('admin')->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Search analytics, content coverage, and technical-SEO health — all in one place.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToSeoSettings')
                ->label('Back to SEO Control Center')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->outlined()
                ->url(SeoControlCenter::getUrl()),
        ];
    }
}
