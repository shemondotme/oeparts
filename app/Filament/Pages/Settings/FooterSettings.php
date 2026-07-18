<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FooterSettings extends SettingsPage
{
    protected static ?string $title = 'Footer Trust Badges';

    protected static string $settingsGroup = 'footer';

    protected static ?int $navigationSort = 26;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-rectangle-group';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Trust Badges')
                    ->description('The four trust badges in the storefront footer. Leave a field empty to use the built-in default.')
                    ->schema([
                        Forms\Components\TextInput::make('oem_badge_text')->label('OEM Badge — Title')->placeholder('Genuine OEM')->maxLength(60),
                        Forms\Components\TextInput::make('oem_badge_subtext')->label('OEM Badge — Subtitle')->maxLength(100),
                        Forms\Components\TextInput::make('shipping_badge_text')->label('Shipping Badge — Title')->maxLength(60),
                        Forms\Components\TextInput::make('shipping_badge_subtext')->label('Shipping Badge — Subtitle')->maxLength(100),
                        Forms\Components\TextInput::make('returns_badge_text')->label('Returns Badge — Title')->maxLength(60),
                        Forms\Components\TextInput::make('returns_badge_subtext')->label('Returns Badge — Subtitle')->maxLength(100),
                        Forms\Components\TextInput::make('security_badge_text')->label('Security Badge — Title')->maxLength(60),
                        Forms\Components\TextInput::make('security_badge_subtext')->label('Security Badge — Subtitle')->maxLength(100),
                    ])->columns(2),
                Section::make('Footer Stats & Payments')
                    ->description('The stats strip and accepted-payment labels in the footer.')
                    ->schema([
                        Forms\Components\TextInput::make('stat_parts_label')
                            ->label('Stat — OEM Numbers Label')
                            ->placeholder('OEM Numbers')
                            ->helperText('Leave empty to use the built-in translated default ("OEM Numbers" / "OEM-Nummern" / …).')
                            ->maxLength(40),
                        Forms\Components\TextInput::make('stat_parts')
                            ->label('Stat — OEM Numbers Value')
                            ->placeholder('e.g. 1.2M+')
                            ->helperText('Should match Stats Counter Settings → Parts Count (the same "OEM numbers sourced" figure shown on the homepage stats section).')
                            ->maxLength(60),
                        Forms\Components\TextInput::make('stat_countries_label')
                            ->label('Stat — Countries Label')
                            ->placeholder('Countries')
                            ->helperText('Leave empty to use the built-in translated default.')
                            ->maxLength(40),
                        Forms\Components\TextInput::make('stat_countries')
                            ->label('Stat — Countries Value')
                            ->placeholder('e.g. 27')
                            ->helperText('Should match Stats Counter Settings → Countries Count — shown identically on the homepage hero pill and the search console page.')
                            ->maxLength(60),
                        Forms\Components\TextInput::make('stat_languages_label')
                            ->label('Stat — Languages Label')
                            ->placeholder('Languages')
                            ->helperText('Leave empty to use the built-in translated default.')
                            ->maxLength(40),
                        Forms\Components\TextInput::make('stat_languages')
                            ->label('Stat — Languages Value')
                            ->placeholder('e.g. 5 languages')
                            ->maxLength(60),
                        Forms\Components\TagsInput::make('payment_methods')
                            ->label('Accepted Payment Labels')
                            ->placeholder('Add a label…')
                            ->helperText('Shown as badges in the footer (e.g. VISA, MASTERCARD, SEPA). Leave empty for the defaults.')
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }
}
