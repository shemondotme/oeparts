<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Clusters\Reports;
use Filament\Pages\Page;

class CheckoutDropoffReport extends Page
{
    protected static ?string $cluster = Reports::class;

    protected static ?string $title = 'Checkout Drop-off';

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && ($user->hasRole('super_admin') || $user->hasRole('admin'));
    }

    protected ?string $subheading = 'Funnel performance and checkout drop-off rate tracking.';

    protected string $view = 'filament.pages.reports.checkout-dropoff-report';

    public string $period = '30';

    protected static bool $shouldRegisterNavigation = true;

    public static function getNavigationLabel(): string
    {
        return 'Checkout Drop-off';
    }

    public static function getNavigationSort(): ?int
    {
        return 40;
    }


    // The funnel KPIs and bar chart are native Filament widgets
    // (App\Filament\Widgets\Reports\Checkout*), rendered by the page view.
}
