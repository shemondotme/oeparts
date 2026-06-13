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
                    ->description('Choose colors used across storefront headers, primary call-to-actions, and accent borders.')
                    ->schema([
                        Forms\Components\ColorPicker::make('primary_color')
                            ->label('Primary Brand Color')
                            ->helperText('Main brand identity color (buttons, active tabs, header highlights)')
                            ->default('#0B3A68'),

                        Forms\Components\ColorPicker::make('accent_color')
                            ->label('Accent/Highlight Color')
                            ->helperText('Used for alert bars, important callouts, badges, or rating stars')
                            ->default('#F59E0B'),
                    ])->columns(2),

                Section::make('Custom CSS Injection')
                    ->description('Add bespoke CSS stylesheets to customize the frontend appearance. Injected directly into the document head.')
                    ->schema([
                        Forms\Components\Toggle::make('custom_css_enabled')
                            ->label('Enable Custom CSS Stylesheet')
                            ->helperText('When enabled, the rules below are loaded on all public storefront pages')
                            ->default(false),

                        Forms\Components\Textarea::make('custom_css')
                            ->label('Stylesheet Rules')
                            ->helperText('Write valid CSS. e.g. body { background-color: #fafafa; }')
                            ->rows(8)
                            ->columnSpanFull()
                            ->default(null),
                    ]),
            ]);
    }
}
