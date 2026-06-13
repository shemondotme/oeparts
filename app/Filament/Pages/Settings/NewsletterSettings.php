<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class NewsletterSettings extends SettingsPage
{
    protected static ?string $title = 'Newsletter Settings';

    protected static string $settingsGroup = 'newsletter';

    protected static ?int $navigationSort = 19;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Newsletter Configuration')
                    ->description('Control subscription behavior and rate limiting.')
                    ->schema([
                        Forms\Components\TextInput::make('rate_limit_per_hour')
                            ->label('Max Subscriptions Per IP Per Hour')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->default(10),

                        Forms\Components\TextInput::make('rate_window_seconds')
                            ->label('Rate Window (seconds)')
                            ->numeric()
                            ->minValue(60)
                            ->maxValue(3600)
                            ->default(3600),

                        Forms\Components\Toggle::make('double_opt_in')
                            ->label('Double Opt-In')
                            ->helperText('Require email confirmation before subscribing')
                            ->default(true),
                    ])->columns(2),
            ]);
    }
}
