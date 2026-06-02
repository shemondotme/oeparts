<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class ShippingSettings extends SettingsPage
{
    protected static ?string $title = 'Shipping Settings';

    protected static string $settingsGroup = 'shipping';

    protected static ?int $navigationSort = 12;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Shipping Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('free_shipping_threshold')
                            ->label('Free Shipping Threshold (€)')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€')
                            ->default(150),
                        Forms\Components\Toggle::make('nudge_enabled')
                            ->label('Enable Free Shipping Nudge')
                            ->default(true),
                        Forms\Components\TextInput::make('nudge_threshold')
                            ->label('Nudge Threshold (€)')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€')
                            ->default(10),
                        Forms\Components\Textarea::make('nudge_text')
                            ->label('Nudge Text (Multilang JSON)')
                            ->helperText('Use {amount} placeholder. JSON with en, de, lt, fr, es keys.')
                            ->rows(2)
                            ->columnSpanFull()
                            ->default(null),
                        Forms\Components\TimePicker::make('cutoff_time')
                            ->label('Order Cutoff Time')
                            ->helperText('Orders after this time ship next business day')
                            ->default('15:00'),
                        Forms\Components\Select::make('cutoff_timezone')
                            ->label('Cutoff Timezone')
                            ->options([
                                'Europe/Vilnius' => 'Europe/Vilnius (UTC+2)',
                                'Europe/Berlin' => 'Europe/Berlin (UTC+1)',
                                'Europe/Paris' => 'Europe/Paris (UTC+1)',
                                'Europe/Madrid' => 'Europe/Madrid (UTC+1)',
                                'Europe/London' => 'Europe/London (UTC+0)',
                                'Europe/Rome' => 'Europe/Rome (UTC+1)',
                                'Europe/Amsterdam' => 'Europe/Amsterdam (UTC+1)',
                                'Europe/Warsaw' => 'Europe/Warsaw (UTC+1)',
                                'Europe/Prague' => 'Europe/Prague (UTC+1)',
                                'Europe/Stockholm' => 'Europe/Stockholm (UTC+1)',
                                'Europe/Copenhagen' => 'Europe/Copenhagen (UTC+1)',
                                'Europe/Helsinki' => 'Europe/Helsinki (UTC+2)',
                                'Europe/Riga' => 'Europe/Riga (UTC+2)',
                                'Europe/Tallinn' => 'Europe/Tallinn (UTC+2)',
                                'UTC' => 'UTC',
                            ])
                            ->searchable()
                            ->default('Europe/Vilnius'),
                        Forms\Components\TextInput::make('handling_fee')
                            ->label('Handling Fee (€)')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€')
                            ->default(0),
                    ])->columns(2),
            ]);
    }
}
