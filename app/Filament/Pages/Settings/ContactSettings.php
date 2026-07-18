<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Support\AdminUi;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

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
                        AdminUi::translatableTabs('Business Hours', [
                            'hours' => [
                                'label' => 'Hours',
                                'type' => 'textarea',
                                'rows' => 2,
                                'helperText' => '',
                                'placeholders' => [
                                    'en' => 'e.g. Mon-Fri: 8:00 - 17:00, Sat: Closed',
                                    'de' => 'e.g. Mo-Fr: 8:00 - 17:00, Sa: Geschlossen',
                                    'lt' => 'e.g. I-V: 8:00 - 17:00, VI: Nedirbame',
                                    'fr' => 'e.g. Lun-Ven: 8h00 - 17h00, Sam: Fermé',
                                    'es' => 'e.g. Lun-Vie: 8:00 - 17:00, Sáb: Cerrado',
                                ],
                            ],
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
                        Forms\Components\Placeholder::make('social_profiles_note')
                            ->label('')
                            ->content(new \Illuminate\Support\HtmlString(
                                'Social profile links (Facebook, LinkedIn, and 4 more platforms), footer display, and icon style are managed on the <a href="'
                                . SocialLinkSettings::getUrl()
                                . '" class="fi-link text-primary-600">Social Links</a> page.'
                            )),
                    ]),

                Section::make('Form Submission — Success Message')
                    ->description('Shown to the customer in their own language immediately after a successful submission. Leave a locale blank to fall back to English.')
                    ->schema([
                        AdminUi::translatableTabs('Success Message', [
                            'success_message' => [
                                'label' => 'Success Message',
                                'type' => 'textarea',
                                'rows' => 2,
                                'placeholders' => [
                                    'en' => 'Your message has been sent successfully. We will get back to you soon.',
                                    'de' => 'Ihre Nachricht wurde erfolgreich gesendet. Wir melden uns in Kürze bei Ihnen.',
                                    'lt' => 'Jūsų žinutė sėkmingai išsiųsta. Netrukus su jumis susisieksime.',
                                    'fr' => 'Votre message a été envoyé avec succès. Nous reviendrons vers vous sous peu.',
                                    'es' => 'Su mensaje se ha enviado correctamente. Nos pondremos en contacto con usted en breve.',
                                ],
                            ],
                        ]),
                    ]),

                Section::make('Abuse Protection')
                    ->description('Rate limiting for contact form submissions.')
                    ->schema([
                        Forms\Components\TextInput::make('rate_limit_per_minute')
                            ->label('Rate Limit (submissions / minute / IP)')
                            ->helperText('Maximum contact form submissions allowed per IP address per minute.')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->default(5),
                    ]),
            ]);
    }
}
