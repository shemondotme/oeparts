<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Support\AdminUi;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

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

                        AdminUi::translatableTabs('Announcement Text', [
                            'text' => [
                                'label' => 'Text',
                                'type' => 'textarea',
                                'rows' => 2,
                                'helperText' => '',
                                'placeholders' => [
                                    'en' => 'e.g. Special offer: Free shipping on orders over €150!',
                                    'de' => 'e.g. Sonderangebot: Kostenloser Versand ab 150 €!',
                                    'lt' => 'e.g. Specialus pasiūlymas: Nemokamas pristatymas nuo 150 €!',
                                    'fr' => 'e.g. Offre spéciale : Livraison gratuite à partir de 150 € !',
                                    'es' => 'e.g. ¡Oferta especial: Envío gratis en pedidos superiores a 150 €!',
                                ],
                            ],
                        ]),

                        AdminUi::translatableTabs('CTA Button Text', [
                            'cta_text' => [
                                'label' => 'CTA',
                                'helperText' => '',
                                'placeholders' => [
                                    'en' => 'e.g. Shop Now',
                                    'de' => 'e.g. Jetzt kaufen',
                                    'lt' => 'e.g. Pirkite dabar',
                                    'fr' => 'e.g. Acheter maintenant',
                                    'es' => 'e.g. Comprar ahora',
                                ],
                            ],
                        ])->helperText('Optional call-to-action button text displayed on the announcement bar.'),

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
