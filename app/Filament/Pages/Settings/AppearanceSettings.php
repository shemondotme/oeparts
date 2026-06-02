<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class AppearanceSettings extends SettingsPage
{
    protected static ?string $title = 'Appearance & Branding';

    protected static string $settingsGroup = 'appearance';

    protected static ?int $navigationSort = 31;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-paint-brush';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Brand Colors')
                    ->schema([
                        Forms\Components\ColorPicker::make('primary_color')
                            ->label('Primary Color')
                            ->helperText('Main brand color for buttons, headings, links')
                            ->default('#0B3A68'),
                        Forms\Components\ColorPicker::make('accent_color')
                            ->label('Accent Color')
                            ->helperText('Used for CTAs, badges, highlights')
                            ->default('#F59E0B'),
                    ])->columns(2),

                Section::make('Custom CSS')
                    ->schema([
                        Forms\Components\Toggle::make('custom_css_enabled')
                            ->label('Enable Custom CSS')
                            ->default(false),
                        Forms\Components\Textarea::make('custom_css')
                            ->label('Custom CSS')
                            ->helperText('Add custom CSS rules. Injected into <head> when enabled.')
                            ->rows(10)
                            ->columnSpanFull()
                            ->default(null),
                    ]),
            ]);
    }
}
