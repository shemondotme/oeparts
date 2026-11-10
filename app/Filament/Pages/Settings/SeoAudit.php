<?php

namespace App\Filament\Pages\Settings;

use App\Jobs\CheckExternalLinks;
use App\Services\SeoAuditService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

/**
 * SEO Audit — one list of concrete SEO problems with how serious each is,
 * how many items it affects, a sample, and a link to where it is fixed.
 * Reached from the SEO Control Center / Health Dashboard headers (and the
 * settings hub); it holds no settings of its own.
 */
class SeoAudit extends Page
{
    protected string $view = 'filament.pages.settings.seo-audit';

    protected static ?string $title = 'SEO Audit';

    protected static ?string $slug = 'seo-settings/audit';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array{checks: list<array<string, mixed>>, summary: array<string, int>}|null */
    public ?array $report = null;

    /** Mirrors SeoHealthDashboard::canAccess(). */
    public static function canAccess(): bool
    {
        return auth('admin')->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Concrete problems and opportunities across configuration, content and redirects — most serious first.';
    }

    public function mount(): void
    {
        $this->report = app(SeoAuditService::class)->run();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rerun')
                ->label('Run the audit again')
                ->icon('heroicon-o-arrow-path')
                ->action(function (): void {
                    Cache::forget(SeoAuditService::IMAGES_CACHE_KEY);
                    Cache::forget(SeoAuditService::LINKS_CACHE_KEY);
                    $this->report = app(SeoAuditService::class)->run();

                    Notification::make()->title('Audit refreshed')->success()->send();
                }),
            Action::make('checkExternalLinks')
                ->label('Check external links now')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Visits the websites your pages and posts link to (politely, one at a time) to find dead links. It can take a few minutes, so it runs in the background — the result appears here when it is done.')
                ->action(function (): void {
                    CheckExternalLinks::dispatch(auth('admin')->id());

                    Notification::make()->title('Link check started')->body('You will get a notification here when it is done.')->success()->send();
                }),
            Action::make('backToSeoSettings')
                ->label('Back to SEO Control Center')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->outlined()
                ->url(SeoControlCenter::getUrl()),
        ];
    }
}
