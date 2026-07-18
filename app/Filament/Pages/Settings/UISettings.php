<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Support\AdminUi;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class UISettings extends SettingsPage
{
    protected static ?string $title = 'UI Settings';

    /**
     * Without this, Filament auto-derives the slug from the class name by
     * kebab-casing each capital letter run, producing "u-i-settings" instead
     * of "ui-settings" — confirmed via `php artisan route:list` before this
     * fix. SEOSettings has the same kind of override for the same reason.
     */
    protected static ?string $slug = 'ui-settings';

    protected static string $settingsGroup = 'ui';

    protected static ?int $navigationSort = 24;

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
                        AdminUi::translatableTabs('Hero Text Locales', [
                            'hero_index_badge' => ['label' => 'Index Badge'],
                            'hero_live_status' => ['label' => 'Live Status'],
                            'hero_eyebrow' => ['label' => 'Eyebrow'],
                            'hero_subtext_default' => ['label' => 'Subtext Default', 'type' => 'textarea', 'rows' => 3],
                            'hero_spec_title' => ['label' => 'Spec Title'],
                            'hero_source_label' => ['label' => 'Source Label'],
                            'hero_source_badge' => ['label' => 'Source Badge'],
                            'hero_search_strip' => ['label' => 'Search Strip'],
                            'hero_search_meta_hint' => ['label' => 'Search Meta Hint'],
                            'hero_indexed_label' => ['label' => 'Indexed Label'],
                        ]),
                    ]),

                Section::make('Spec Table')
                    ->description('Configure the specification table labels and values.')
                    ->schema([
                        AdminUi::translatableTabs('Spec Table Locales', [
                            'hero_spec_r1_label' => ['label' => 'Row 1 Label'],
                            'hero_spec_r2_label' => ['label' => 'Row 2 Label'],
                            'hero_spec_r2_value' => ['label' => 'Row 2 Value'],
                            'hero_spec_r3_label' => ['label' => 'Row 3 Label'],
                            'hero_spec_r3_value' => ['label' => 'Row 3 Value'],
                            'hero_spec_r4_label' => ['label' => 'Row 4 Label'],
                            'hero_spec_r4_value' => ['label' => 'Row 4 Value', 'helperText' => 'Also shown as the dispatch-time trust badge on the search zero-results page — kept in sync automatically, no separate field to update.'],
                            'hero_spec_r5_label' => ['label' => 'Row 5 Label'],
                            'hero_spec_r5_value' => ['label' => 'Row 5 Value'],
                        ]),
                    ]),

                Section::make('Footer Pills')
                    ->description('Configure the three footer pill badges beneath the hero.')
                    ->schema([
                        AdminUi::translatableTabs('Footer Pills Locales', [
                            'hero_footer_pill_1' => ['label' => 'Pill 1'],
                            'hero_footer_pill_2' => ['label' => 'Pill 2'],
                            'hero_footer_pill_3' => ['label' => 'Pill 3', 'helperText' => 'If this states a countries count, keep the number in sync with Stats Counter Settings → Countries Count (also shown in the footer and search console page).'],
                        ]),
                    ]),
            ]);
    }
}
