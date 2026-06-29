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
                            ->helperText('Hours of inactivity before a cart is counted as abandoned on the dashboard')
                            ->default(2),
                    ])->columns(2),
            ]);
    }
}
