<?php

namespace App\Livewire;

use App\Filament\Pages\System\HealthCheckDashboard;
use App\Services\HealthCheckService;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class HealthIndicator extends Component
{
    public function render()
    {
        $status = Cache::remember(
            'admin_health_indicator',
            30,
            fn () => app(HealthCheckService::class)->runAll()['status']
        );

        $admin = auth('admin')->user();
        $canViewDashboard = $admin && $admin->hasRole('super_admin');

        return view('livewire.health-indicator', [
            'status' => $status,
            'url' => $canViewDashboard ? HealthCheckDashboard::getUrl() : null,
        ]);
    }
}
