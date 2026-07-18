<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Support\AdminUi;
use App\Filament\Resources\ShippingZoneResource;
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
                Section::make('Shipping Thresholds & Fees')
                    ->description('Set nudge goals and basic handling charges. Free shipping thresholds are set per shipping method.')
                    ->schema([
                        Forms\Components\Placeholder::make('free_shipping_threshold_note')
                            ->label('')
                            ->columnSpanFull()
                            ->content(new \Illuminate\Support\HtmlString(
                                'Free shipping thresholds are set per shipping method, not globally — configure them on the <a href="'
                                . ShippingZoneResource::getUrl()
                                . '" class="fi-link text-primary-600">Shipping Zones</a> page (each method has its own "Free Shipping Threshold" field).'
                            )),

                        Forms\Components\TextInput::make('handling_fee')
                            ->label('Standard Order Handling Fee')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€')
                            ->placeholder('0.00')
                            ->default(0),

                        Forms\Components\Toggle::make('nudge_enabled')
                            ->label('Enable Free Shipping Nudge Alert')
                            ->helperText('Displays a notice in cart showing how much more is needed for free shipping')
                            ->default(true),

                        Forms\Components\TextInput::make('nudge_threshold')
                            ->label('Nudge Trigger Remaining Amount')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€')
                            ->placeholder('10')
                            ->helperText('Trigger nudge only when remaining amount is below this value')
                            ->default(10),

                        AdminUi::translatableTabs('Free Shipping Nudge Text', [
                            'nudge_text' => [
                                'label' => 'Nudge',
                                'type' => 'textarea',
                                'rows' => 2,
                                'placeholders' => [
                                    'en' => 'Add only €{amount} more to get free shipping!',
                                    'de' => 'Fügen Sie noch €{amount} hinzu, um kostenlosen Versand zu erhalten!',
                                    'lt' => 'Pridėkite dar €{amount} nemokamam pristatymui!',
                                    'fr' => 'Ajoutez €{amount} de plus pour la livraison gratuite !',
                                    'es' => '¡Añade €{amount} más para conseguir envío gratis!',
                                ],
                            ],
                        ]),

                        // Checkout step 3's shipping note (resources/views/frontend/checkout/step3.blade.php)
                        // read this key via settings() with a hardcoded English default and no admin
                        // field ever existed for it — it could never actually be changed or translated.
                        AdminUi::translatableTabs('Shipping Note (checkout step 3)', [
                            'note_text' => [
                                'label' => 'Note',
                                'type' => 'textarea',
                                'rows' => 2,
                                'placeholders' => [
                                    'en' => 'All shipments tracked and insured. Delivery times are estimates from dispatch.',
                                    'de' => 'Alle Sendungen werden verfolgt und sind versichert. Lieferzeiten sind Schätzungen ab Versand.',
                                    'lt' => 'Visos siuntos sekamos ir apdraustos. Pristatymo laikas skaičiuojamas nuo išsiuntimo.',
                                    'fr' => 'Tous les envois sont suivis et assurés. Les délais de livraison sont estimés à partir de l\'expédition.',
                                    'es' => 'Todos los envíos son rastreados y están asegurados. Los plazos de entrega son estimaciones desde el envío.',
                                ],
                            ],
                        ]),
                    ])->columns(2),

                Section::make('Business Days & Origin')
                    ->description('Define which days are considered business days and the default origin country for shipping calculations.')
                    ->schema([
                        Forms\Components\TagsInput::make('business_days')
                            ->label('Business Days')
                            ->helperText('Days when orders are processed and shipped. Used for estimated delivery calculations.')
                            ->suggestions(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'])
                            ->default(['Mon', 'Tue', 'Wed', 'Thu', 'Fri']),

                        Forms\Components\Select::make('default_origin_country')
                            ->label('Default Origin Country')
                            ->helperText('Used as the default ship-from country for rate calculations.')
                            ->options([
                                'LT' => 'Lithuania',
                                'DE' => 'Germany',
                                'PL' => 'Poland',
                                'LV' => 'Latvia',
                                'EE' => 'Estonia',
                                'NL' => 'Netherlands',
                                'BE' => 'Belgium',
                                'FR' => 'France',
                                'ES' => 'Spain',
                                'IT' => 'Italy',
                                'CZ' => 'Czech Republic',
                                'AT' => 'Austria',
                                'SE' => 'Sweden',
                                'FI' => 'Finland',
                                'IE' => 'Ireland',
                                'PT' => 'Portugal',
                                'SI' => 'Slovenia',
                                'SK' => 'Slovakia',
                                'HU' => 'Hungary',
                                'HR' => 'Croatia',
                                'BG' => 'Bulgaria',
                                'RO' => 'Romania',
                                'DK' => 'Denmark',
                                'NO' => 'Norway',
                                'CH' => 'Switzerland',
                                'GB' => 'United Kingdom',
                            ])
                            ->searchable()
                            ->default('LT'),
                    ])->columns(2),

                Section::make('Fulfillment Cutoffs')
                    ->description('Specify operational cutoff rules for daily shipments.')
                    ->schema([
                        Forms\Components\TimePicker::make('cutoff_time')
                            ->label('Fulfillment Cutoff Time')
                            ->helperText('Orders placed after this time are processed the next business day')
                            ->default('15:00'),

                        Forms\Components\Select::make('cutoff_timezone')
                            ->label('Operational Timezone')
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
                    ])->columns(2),
            ]);
    }
}
