<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

class ContactSettings extends SettingsPage
{
    protected static ?string $title = 'Contact Information';

    protected static string $settingsGroup = 'contact';

    protected static ?int $navigationSort = 34;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-phone';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contact Details')
                    ->description('Store locations and public-facing contact endpoints.')
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->helperText('Support/customer service phone. For the public header phone, use General Settings → Public Contact Phone.')
                            ->tel()
                            ->maxLength(30)
                            ->placeholder('+370 600 00000')
                            ->default(null),

                        Forms\Components\TextInput::make('email')
                            ->label('Contact Email')
                            ->helperText('Support/customer service email. For the public header email, use General Settings → Public Contact Email.')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('support@oeparts.lt')
                            ->default(null),

                        Forms\Components\Textarea::make('address')
                            ->label('Business Address')
                            ->helperText('Support/business location address. For the registered address, use General Settings → Registered Address.')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('e.g. Ulonų g. 5, Vilnius, Lithuania')
                            ->columnSpanFull()
                            ->default(null),
                    ])->columns(2),

                Section::make('Business Hours')
                    ->description('Operating hours rendered across footer and contact pages.')
                    ->schema([
                        Tabs::make('Business Hours')
                            ->statePath('hours')
                            ->columnSpanFull()
                            ->tabs([
                                Tab::make('English')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\Textarea::make('en')
                                            ->label('Hours (EN)')
                                            ->rows(2)
                                            ->placeholder('e.g. Mon-Fri: 8:00 - 17:00, Sat: Closed'),
                                    ]),
                                Tab::make('Deutsch')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\Textarea::make('de')
                                            ->label('Hours (DE)')
                                            ->rows(2)
                                            ->placeholder('e.g. Mo-Fr: 8:00 - 17:00, Sa: Geschlossen'),
                                    ]),
                                Tab::make('Lietuvių')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\Textarea::make('lt')
                                            ->label('Hours (LT)')
                                            ->rows(2)
                                            ->placeholder('e.g. I-V: 8:00 - 17:00, VI: Nedirbame'),
                                    ]),
                                Tab::make('Français')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\Textarea::make('fr')
                                            ->label('Hours (FR)')
                                            ->rows(2)
                                            ->placeholder('e.g. Lun-Ven: 8h00 - 17h00, Sam: Fermé'),
                                    ]),
                                Tab::make('Español')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\Textarea::make('es')
                                            ->label('Hours (ES)')
                                            ->rows(2)
                                            ->placeholder('e.g. Lun-Vie: 8:00 - 17:00, Sáb: Cerrado'),
                                    ]),
                            ]),
                    ]),

                Section::make('Messaging Channels')
                    ->description('Quick chat links or hotline numbers for B2B buyer assistance.')
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp')
                            ->label('WhatsApp Number')
                            ->tel()
                            ->placeholder('+370 600 00000')
                            ->maxLength(30)
                            ->default(null),

                        Forms\Components\TextInput::make('viber')
                            ->label('Viber Number')
                            ->tel()
                            ->placeholder('+370 600 00000')
                            ->maxLength(30)
                            ->default(null),
                    ])->columns(2),

                Section::make('Social Profiles')
                    ->description('Links to verified company social pages.')
                    ->schema([
                        Forms\Components\TextInput::make('facebook_url')
                            ->label('Facebook Page URL')
                            ->helperText('Quick link for contact page. For full social link management (display, footer icons), use the dedicated Social Links settings page.')
                            ->url()
                            ->placeholder('https://facebook.com/oeparts')
                            ->maxLength(500)
                            ->default(null),

                        Forms\Components\TextInput::make('linkedin_url')
                            ->label('LinkedIn Company URL')
                            ->helperText('Quick link for contact page. For full social link management (display, footer icons), use the dedicated Social Links settings page.')
                            ->url()
                            ->placeholder('https://linkedin.com/company/oeparts')
                            ->maxLength(500)
                            ->default(null),
                    ])->columns(2),
            ]);
    }
}
