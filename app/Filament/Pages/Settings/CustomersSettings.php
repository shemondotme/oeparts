<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomersSettings extends SettingsPage
{
    protected static ?string $title = 'Customer Settings';

    protected static string $settingsGroup = 'customers';

    protected static ?int $navigationSort = 33;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-users';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer Segments')
                    ->description('Thresholds behind the Segment badge on the Customers list (VIP / Repeat / Regular / Prospect).')
                    ->schema([
                        Forms\Components\TextInput::make('vip_min_orders')
                            ->label('VIP — Minimum Orders')
                            ->numeric()
                            ->minValue(1)
                            ->default(10)
                            ->helperText('A customer needs at least this many orders (and the spend below) to rank as VIP.'),
                        Forms\Components\TextInput::make('vip_min_spent')
                            ->label('VIP — Minimum Total Spend (€)')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€')
                            ->default(1000)
                            ->helperText('Total paid-order spend required for VIP, together with the order count above.'),
                        Forms\Components\TextInput::make('repeat_min_orders')
                            ->label('Repeat — Minimum Orders')
                            ->numeric()
                            ->minValue(2)
                            ->default(3)
                            ->helperText('Customers with at least this many orders (but below VIP) rank as Repeat.'),
                    ])->columns(3),
            ]);
    }
}
