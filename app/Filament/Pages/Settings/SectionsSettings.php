<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class SectionsSettings extends SettingsPage
{
    protected static ?string $title = 'Sections Settings';

    protected static string $settingsGroup = 'sections';

    protected static ?int $navigationSort = 26;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Content Limits')
                    ->description('Control how many items are displayed in each homepage section type.')
                    ->schema([
                        Forms\Components\TextInput::make('testimonials_limit')
                            ->label('Testimonials Section Limit')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->default(6),

                        Forms\Components\TextInput::make('faq_limit')
                            ->label('FAQ Section Limit')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->default(10),

                        Forms\Components\TextInput::make('blog_limit')
                            ->label('Blog Section Limit')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(20)
                            ->default(3),

                        Forms\Components\TextInput::make('manufacturers_limit')
                            ->label('Manufacturers Section Limit')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->default(12),
                    ])->columns(2),
            ]);
    }
}
