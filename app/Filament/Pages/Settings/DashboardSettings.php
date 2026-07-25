<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class DashboardSettings extends SettingsPage
{
    protected static ?string $title = 'Dashboard Thresholds';

    protected static string $settingsGroup = 'dashboard';

    protected static ?int $navigationSort = 35;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-presentation-chart-line';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Admin Dashboard Alert Thresholds')
                    ->description('Tune the warning thresholds used by dashboard widgets to flag attention-needed orders and abandoned carts.')
                    ->schema([
                        Forms\Components\TextInput::make('orders_threshold')
                            ->label('Pending Orders Alert Threshold')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10000)
                            ->required()
                            ->helperText('Pending order count that triggers the dashboard attention indicator')
                            ->default(50),

                        Forms\Components\TextInput::make('pending_delayed_minutes')
                            ->label('Delayed Order Threshold (Minutes)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1440)
                            ->required()
                            ->helperText('Minutes a pending order can sit before being flagged as delayed')
                            ->default(120),

                        Forms\Components\TextInput::make('cart_abandoned_hours')
                            ->label('Cart Abandonment Threshold (Hours)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(720)
                            ->required()
                            ->helperText('Hours of inactivity before a cart counts as abandoned — drives the dashboard widget and the hourly recovery-email run')
                            ->default(2),
                    ])->columns(2),

                Section::make('Health Check Thresholds')
                    ->description('Tune when the System → Health Check page flags the scheduler or backups as stale.')
                    ->schema([
                        Forms\Components\TextInput::make('scheduler_stale_minutes')
                            ->label('Scheduler Heartbeat Stale Threshold (Minutes)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->required()
                            ->helperText('Minutes since the last cron heartbeat before the Scheduler check is flagged stale')
                            ->default(3),

                        Forms\Components\TextInput::make('backup_stale_hours')
                            ->label('Backup Stale Threshold (Hours)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(168)
                            ->required()
                            ->helperText('Hours since the last successful backup before the Backup check is flagged stale')
                            ->default(26),
                    ])->columns(2),

                Section::make('Cache Dashboard Threshold')
                    ->description('Tune when the System → Cache Dashboard page alerts admins about a degraded hit rate.')
                    ->schema([
                        Forms\Components\TextInput::make('cache_hit_rate_alert_threshold')
                            ->label('Hit Rate Alert Threshold (%)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->required()
                            ->helperText('Admins are notified once when the Redis cache hit rate drops below this percentage')
                            ->default(50),
                    ])->columns(2),
            ]);
    }
}
