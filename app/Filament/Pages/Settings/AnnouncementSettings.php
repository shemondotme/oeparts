<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

class AnnouncementSettings extends SettingsPage
{
    protected static ?string $title = 'Announcement Bar';

    protected static string $settingsGroup = 'announcement';

    protected static ?int $navigationSort = 30;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-megaphone';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Announcement Configuration')
                    ->description('Set up a top banner to promote sales, updates, or maintenance windows.')
                    ->schema([
                        Forms\Components\Toggle::make('enabled')
                            ->label('Enable Announcement Bar')
                            ->helperText('Show this marquee banner at the top of the storefront pages')
                            ->default(false),

                        Forms\Components\Toggle::make('dismissable')
                            ->label('Allow Users to Dismiss')
                            ->helperText('Allow visitors to close the announcement bar during their session')
                            ->default(true),

                        Tabs::make('Announcement Text')
                            ->statePath('text')
                            ->columnSpanFull()
                            ->tabs([
                                Tab::make('English')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\Textarea::make('en')
                                            ->label('Text (EN)')
                                            ->rows(2)
                                            ->placeholder('e.g. Special offer: Free shipping on orders over €150!'),
                                    ]),
                                Tab::make('Deutsch')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\Textarea::make('de')
                                            ->label('Text (DE)')
                                            ->rows(2)
                                            ->placeholder('e.g. Sonderangebot: Kostenloser Versand ab 150 €!'),
                                    ]),
                                Tab::make('Lietuvių')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\Textarea::make('lt')
                                            ->label('Text (LT)')
                                            ->rows(2)
                                            ->placeholder('e.g. Specialus pasiūlymas: Nemokamas pristatymas nuo 150 €!'),
                                    ]),
                                Tab::make('Français')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\Textarea::make('fr')
                                            ->label('Text (FR)')
                                            ->rows(2)
                                            ->placeholder('e.g. Offre spéciale : Livraison gratuite à partir de 150 € !'),
                                    ]),
                                Tab::make('Español')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\Textarea::make('es')
                                            ->label('Text (ES)')
                                            ->rows(2)
                                            ->placeholder('e.g. ¡Oferta especial: Envío gratis en pedidos superiores a 150 €!'),
                                    ]),
                            ]),

                        Tabs::make('CTA Button Text')
                            ->statePath('cta_text')
                            ->columnSpanFull()
                            ->helperText('Optional call-to-action button text displayed on the announcement bar.')
                            ->tabs([
                                Tab::make('English')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('en')
                                            ->label('CTA (EN)')
                                            ->placeholder('e.g. Shop Now'),
                                    ]),
                                Tab::make('Deutsch')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('de')
                                            ->label('CTA (DE)')
                                            ->placeholder('e.g. Jetzt kaufen'),
                                    ]),
                                Tab::make('Lietuvių')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('lt')
                                            ->label('CTA (LT)')
                                            ->placeholder('e.g. Pirkite dabar'),
                                    ]),
                                Tab::make('Français')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('fr')
                                            ->label('CTA (FR)')
                                            ->placeholder('e.g. Acheter maintenant'),
                                    ]),
                                Tab::make('Español')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('es')
                                            ->label('CTA (ES)')
                                            ->placeholder('e.g. Comprar ahora'),
                                    ]),
                            ]),

                        Forms\Components\ColorPicker::make('color')
                            ->label('Background Color')
                            ->helperText('Background color of the banner')
                            ->default('#F59E0B'),

                        Forms\Components\ColorPicker::make('text_color')
                            ->label('Text Color')
                            ->helperText('Text color of the banner')
                            ->default('#1E293B'),

                        Forms\Components\TextInput::make('url')
                            ->label('Link URL (Optional)')
                            ->url()
                            ->maxLength(500)
                            ->helperText('Makes the entire announcement bar clickable to redirect users')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }
}
