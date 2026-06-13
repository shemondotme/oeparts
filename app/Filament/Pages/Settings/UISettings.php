<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

class UISettings extends SettingsPage
{
    protected static ?string $title = 'UI Settings';

    protected static string $settingsGroup = 'ui';

    protected static ?int $navigationSort = 17;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-paint-brush';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Hero Section')
                    ->description('Configure the homepage hero banner text and badges.')
                    ->schema([
                        Tabs::make('Hero Text Locales')
                            ->columnSpanFull()
                            ->tabs([
                                Tab::make('English')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('hero_index_badge.en')
                                            ->label('Index Badge (EN)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_live_status.en')
                                            ->label('Live Status (EN)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_eyebrow.en')
                                            ->label('Eyebrow (EN)')
                                            ->maxLength(255),
                                        Forms\Components\Textarea::make('hero_subtext_default.en')
                                            ->label('Subtext Default (EN)')
                                            ->rows(3),
                                        Forms\Components\TextInput::make('hero_spec_title.en')
                                            ->label('Spec Title (EN)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_source_label.en')
                                            ->label('Source Label (EN)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_source_badge.en')
                                            ->label('Source Badge (EN)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_search_strip.en')
                                            ->label('Search Strip (EN)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_search_meta_hint.en')
                                            ->label('Search Meta Hint (EN)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_indexed_label.en')
                                            ->label('Indexed Label (EN)')
                                            ->maxLength(255),
                                    ]),
                                Tab::make('Deutsch')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('hero_index_badge.de')
                                            ->label('Index Badge (DE)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_live_status.de')
                                            ->label('Live Status (DE)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_eyebrow.de')
                                            ->label('Eyebrow (DE)')
                                            ->maxLength(255),
                                        Forms\Components\Textarea::make('hero_subtext_default.de')
                                            ->label('Subtext Default (DE)')
                                            ->rows(3),
                                        Forms\Components\TextInput::make('hero_spec_title.de')
                                            ->label('Spec Title (DE)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_source_label.de')
                                            ->label('Source Label (DE)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_source_badge.de')
                                            ->label('Source Badge (DE)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_search_strip.de')
                                            ->label('Search Strip (DE)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_search_meta_hint.de')
                                            ->label('Search Meta Hint (DE)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_indexed_label.de')
                                            ->label('Indexed Label (DE)')
                                            ->maxLength(255),
                                    ]),
                                Tab::make('Lietuvių')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('hero_index_badge.lt')
                                            ->label('Index Badge (LT)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_live_status.lt')
                                            ->label('Live Status (LT)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_eyebrow.lt')
                                            ->label('Eyebrow (LT)')
                                            ->maxLength(255),
                                        Forms\Components\Textarea::make('hero_subtext_default.lt')
                                            ->label('Subtext Default (LT)')
                                            ->rows(3),
                                        Forms\Components\TextInput::make('hero_spec_title.lt')
                                            ->label('Spec Title (LT)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_source_label.lt')
                                            ->label('Source Label (LT)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_source_badge.lt')
                                            ->label('Source Badge (LT)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_search_strip.lt')
                                            ->label('Search Strip (LT)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_search_meta_hint.lt')
                                            ->label('Search Meta Hint (LT)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_indexed_label.lt')
                                            ->label('Indexed Label (LT)')
                                            ->maxLength(255),
                                    ]),
                                Tab::make('Français')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('hero_index_badge.fr')
                                            ->label('Index Badge (FR)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_live_status.fr')
                                            ->label('Live Status (FR)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_eyebrow.fr')
                                            ->label('Eyebrow (FR)')
                                            ->maxLength(255),
                                        Forms\Components\Textarea::make('hero_subtext_default.fr')
                                            ->label('Subtext Default (FR)')
                                            ->rows(3),
                                        Forms\Components\TextInput::make('hero_spec_title.fr')
                                            ->label('Spec Title (FR)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_source_label.fr')
                                            ->label('Source Label (FR)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_source_badge.fr')
                                            ->label('Source Badge (FR)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_search_strip.fr')
                                            ->label('Search Strip (FR)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_search_meta_hint.fr')
                                            ->label('Search Meta Hint (FR)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_indexed_label.fr')
                                            ->label('Indexed Label (FR)')
                                            ->maxLength(255),
                                    ]),
                                Tab::make('Español')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('hero_index_badge.es')
                                            ->label('Index Badge (ES)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_live_status.es')
                                            ->label('Live Status (ES)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_eyebrow.es')
                                            ->label('Eyebrow (ES)')
                                            ->maxLength(255),
                                        Forms\Components\Textarea::make('hero_subtext_default.es')
                                            ->label('Subtext Default (ES)')
                                            ->rows(3),
                                        Forms\Components\TextInput::make('hero_spec_title.es')
                                            ->label('Spec Title (ES)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_source_label.es')
                                            ->label('Source Label (ES)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_source_badge.es')
                                            ->label('Source Badge (ES)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_search_strip.es')
                                            ->label('Search Strip (ES)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_search_meta_hint.es')
                                            ->label('Search Meta Hint (ES)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_indexed_label.es')
                                            ->label('Indexed Label (ES)')
                                            ->maxLength(255),
                                    ]),
                            ]),
                    ]),

                Section::make('Spec Table')
                    ->description('Configure the specification table labels and values.')
                    ->schema([
                        Tabs::make('Spec Table Locales')
                            ->columnSpanFull()
                            ->tabs([
                                Tab::make('English')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('hero_spec_r1_label.en')
                                            ->label('Row 1 Label (EN)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r2_label.en')
                                            ->label('Row 2 Label (EN)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r2_value.en')
                                            ->label('Row 2 Value (EN)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r3_label.en')
                                            ->label('Row 3 Label (EN)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r3_value.en')
                                            ->label('Row 3 Value (EN)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r4_label.en')
                                            ->label('Row 4 Label (EN)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r4_value.en')
                                            ->label('Row 4 Value (EN)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r5_label.en')
                                            ->label('Row 5 Label (EN)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r5_value.en')
                                            ->label('Row 5 Value (EN)')
                                            ->maxLength(255),
                                    ]),
                                Tab::make('Deutsch')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('hero_spec_r1_label.de')
                                            ->label('Row 1 Label (DE)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r2_label.de')
                                            ->label('Row 2 Label (DE)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r2_value.de')
                                            ->label('Row 2 Value (DE)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r3_label.de')
                                            ->label('Row 3 Label (DE)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r3_value.de')
                                            ->label('Row 3 Value (DE)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r4_label.de')
                                            ->label('Row 4 Label (DE)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r4_value.de')
                                            ->label('Row 4 Value (DE)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r5_label.de')
                                            ->label('Row 5 Label (DE)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r5_value.de')
                                            ->label('Row 5 Value (DE)')
                                            ->maxLength(255),
                                    ]),
                                Tab::make('Lietuvių')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('hero_spec_r1_label.lt')
                                            ->label('Row 1 Label (LT)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r2_label.lt')
                                            ->label('Row 2 Label (LT)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r2_value.lt')
                                            ->label('Row 2 Value (LT)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r3_label.lt')
                                            ->label('Row 3 Label (LT)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r3_value.lt')
                                            ->label('Row 3 Value (LT)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r4_label.lt')
                                            ->label('Row 4 Label (LT)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r4_value.lt')
                                            ->label('Row 4 Value (LT)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r5_label.lt')
                                            ->label('Row 5 Label (LT)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r5_value.lt')
                                            ->label('Row 5 Value (LT)')
                                            ->maxLength(255),
                                    ]),
                                Tab::make('Français')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('hero_spec_r1_label.fr')
                                            ->label('Row 1 Label (FR)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r2_label.fr')
                                            ->label('Row 2 Label (FR)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r2_value.fr')
                                            ->label('Row 2 Value (FR)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r3_label.fr')
                                            ->label('Row 3 Label (FR)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r3_value.fr')
                                            ->label('Row 3 Value (FR)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r4_label.fr')
                                            ->label('Row 4 Label (FR)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r4_value.fr')
                                            ->label('Row 4 Value (FR)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r5_label.fr')
                                            ->label('Row 5 Label (FR)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r5_value.fr')
                                            ->label('Row 5 Value (FR)')
                                            ->maxLength(255),
                                    ]),
                                Tab::make('Español')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('hero_spec_r1_label.es')
                                            ->label('Row 1 Label (ES)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r2_label.es')
                                            ->label('Row 2 Label (ES)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r2_value.es')
                                            ->label('Row 2 Value (ES)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r3_label.es')
                                            ->label('Row 3 Label (ES)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r3_value.es')
                                            ->label('Row 3 Value (ES)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r4_label.es')
                                            ->label('Row 4 Label (ES)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r4_value.es')
                                            ->label('Row 4 Value (ES)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r5_label.es')
                                            ->label('Row 5 Label (ES)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_spec_r5_value.es')
                                            ->label('Row 5 Value (ES)')
                                            ->maxLength(255),
                                    ]),
                            ]),
                    ]),

                Section::make('Footer Pills')
                    ->description('Configure the three footer pill badges beneath the hero.')
                    ->schema([
                        Tabs::make('Footer Pills Locales')
                            ->columnSpanFull()
                            ->tabs([
                                Tab::make('English')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('hero_footer_pill_1.en')
                                            ->label('Pill 1 (EN)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_footer_pill_2.en')
                                            ->label('Pill 2 (EN)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_footer_pill_3.en')
                                            ->label('Pill 3 (EN)')
                                            ->maxLength(255),
                                    ]),
                                Tab::make('Deutsch')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('hero_footer_pill_1.de')
                                            ->label('Pill 1 (DE)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_footer_pill_2.de')
                                            ->label('Pill 2 (DE)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_footer_pill_3.de')
                                            ->label('Pill 3 (DE)')
                                            ->maxLength(255),
                                    ]),
                                Tab::make('Lietuvių')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('hero_footer_pill_1.lt')
                                            ->label('Pill 1 (LT)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_footer_pill_2.lt')
                                            ->label('Pill 2 (LT)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_footer_pill_3.lt')
                                            ->label('Pill 3 (LT)')
                                            ->maxLength(255),
                                    ]),
                                Tab::make('Français')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('hero_footer_pill_1.fr')
                                            ->label('Pill 1 (FR)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_footer_pill_2.fr')
                                            ->label('Pill 2 (FR)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_footer_pill_3.fr')
                                            ->label('Pill 3 (FR)')
                                            ->maxLength(255),
                                    ]),
                                Tab::make('Español')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('hero_footer_pill_1.es')
                                            ->label('Pill 1 (ES)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_footer_pill_2.es')
                                            ->label('Pill 2 (ES)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('hero_footer_pill_3.es')
                                            ->label('Pill 3 (ES)')
                                            ->maxLength(255),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
