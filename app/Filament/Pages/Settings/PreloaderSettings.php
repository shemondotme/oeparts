<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Support\AdminUi;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class PreloaderSettings extends SettingsPage
{
    protected static ?string $title = 'Preloader Settings';

    protected static string $settingsGroup = 'preloader';

    protected static ?int $navigationSort = 25;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Preloader Configuration')
                    ->description('Control the full-screen loading animation on storefront pages.')
                    ->schema([
                        Forms\Components\Toggle::make('enabled')
                            ->label('Enable Preloader')
                            ->helperText('Show a loading screen on page navigation')
                            ->default(false),

                        Forms\Components\Select::make('path_mode')
                            ->label('Path Mode')
                            ->options([
                                'all' => 'All Pages',
                                'include' => 'Include Only',
                                'exclude' => 'Exclude Only',
                            ])
                            ->default('all'),

                        Forms\Components\TagsInput::make('path_patterns')
                            ->label('Path Patterns')
                            ->helperText('URL patterns to match (e.g., /parts/*, /cart)')
                            ->default([]),

                        Forms\Components\TextInput::make('min_display_ms')
                            ->label('Minimum Display Time (ms)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(5000)
                            ->default(450),

                        Forms\Components\TextInput::make('max_display_ms')
                            ->label('Maximum Display Time (ms)')
                            ->numeric()
                            ->minValue(500)
                            ->maxValue(60000)
                            ->default(6000),
                    ])->columns(2),

                Section::make('Preloader Text')
                    ->description('Customize the multilang text displayed during the preloader animation.')
                    ->schema([
                        AdminUi::translatableTabs('Preloader Text Locales', [
                            'headline' => ['label' => 'Headline'],
                            'spec_line' => ['label' => 'Spec Line'],
                            'subline' => ['label' => 'Subline'],
                            'status_line' => ['label' => 'Status Line'],
                            'foot_left' => ['label' => 'Footer Left'],
                            'foot_right' => ['label' => 'Footer Right'],
                            'aria_label' => ['label' => 'ARIA Label'],
                        ]),
                    ]),
            ]);
    }
}
