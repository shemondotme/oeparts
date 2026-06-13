<?php

namespace App\Filament\Pages\Reports;

use App\Models\Order;
use App\Filament\Clusters\Reports;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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


    public function getCheckoutSteps(): array
    {
        $start = Carbon::now()->subDays((int) $this->period);

        $abandonedCount = \App\Models\AbandonedCart::where('created_at', '>=', $start)->count();
        $completedCount = Order::where('created_at', '>=', $start)->count();
        $startedCheckout = $completedCount + $abandonedCount;

        $paid = Order::where('created_at', '>=', $start)
            ->whereIn('status', ['paid', 'shipped', 'delivered'])
            ->count();
        $cancelled = Order::where('created_at', '>=', $start)
            ->where('status', 'cancelled')
            ->count();

        $total = $startedCheckout;

        return [
            ['step' => 'Started Checkout', 'count' => $total, 'percent' => 100],
            ['step' => 'Completed Order', 'count' => $completedCount, 'percent' => $total > 0 ? round(($completedCount / $total) * 100, 1) : 0],
            ['step' => 'Paid', 'count' => $paid, 'percent' => $total > 0 ? round(($paid / $total) * 100, 1) : 0],
            ['step' => 'Cancelled', 'count' => $cancelled, 'percent' => $total > 0 ? round(($cancelled / $total) * 100, 1) : 0],
            ['step' => 'Abandoned', 'count' => $abandonedCount, 'percent' => $total > 0 ? round(($abandonedCount / $total) * 100, 1) : 0],
        ];
    }

    public function getDropoffRate(): string
    {
        $start = Carbon::now()->subDays((int) $this->period);

        $total = Order::where('created_at', '>=', $start)->count() + \App\Models\AbandonedCart::where('created_at', '>=', $start)->count();
        $paid = Order::where('created_at', '>=', $start)
            ->whereIn('status', ['paid', 'shipped', 'delivered'])
            ->count();

        if ($total === 0) {
            return '0.0';
        }

        $rate = 100 - (($paid / $total) * 100);

        return number_format($rate, 1, '.', '');
    }
}
