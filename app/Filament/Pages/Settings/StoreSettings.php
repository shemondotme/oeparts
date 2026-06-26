<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;

class StoreSettings extends SettingsPage
{
    protected static ?string $title = 'Store Settings';

    protected static string $settingsGroup = 'store';

    protected static ?int $navigationSort = 6;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Currency Configuration')
                    ->description('Set the base currency for your store and formatting preferences.')
                    ->schema([
                        Placeholder::make('current_currency_display')
                            ->label('Store Currency')
                            ->content(function () {
                                return settings('general.currency', 'EUR') . ' (' . settings('general.currency_symbol', '€') . ')';
                            })
                            ->helperText('Currency is configured in General Settings. This is a read-only reference.'),

                        Forms\Components\Select::make('currency_position')
                            ->label('Symbol Position')
                            ->options([
                                'before' => 'Before amount (€100)',
                                'after' => 'After amount (100€)',
                            ])
                            ->default('after'),
                    ])->columns(2),

                Section::make('Locale & Formatting')
                    ->description('Regional formatting rules for prices, dates, and numbers.')
                    ->schema([
                        Forms\Components\Select::make('decimal_separator')
                            ->label('Decimal Separator')
                            ->options([
                                '.' => 'Dot (12.34)',
                                ',' => 'Comma (12,34)',
                            ])
                            ->default('.'),

                        Forms\Components\Select::make('thousand_separator')
                            ->label('Thousand Separator')
                            ->options([
                                ',' => 'Comma (1,234)',
                                '.' => 'Dot (1.234)',
                                ' ' => 'Space (1 234)',
                            ])
                            ->default(','),
                    ])->columns(2),
            ]);
    }
}
