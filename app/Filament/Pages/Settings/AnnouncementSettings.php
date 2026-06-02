<?php

namespace App\Filament\Pages\Settings;

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
                    ->schema([
                        Forms\Components\Toggle::make('enabled')
                            ->label('Enable Announcement Bar')
                            ->helperText('Show a banner at the top of the storefront')
                            ->default(false),
                        Forms\Components\Textarea::make('text')
                            ->label('Announcement Text (Multilang JSON)')
                            ->helperText('JSON object with keys: en, de, lt, fr, es')
                            ->rows(3)
                            ->columnSpanFull()
                            ->default(null),
                        Forms\Components\ColorPicker::make('color')
                            ->label('Background Color')
                            ->default('#F59E0B'),
                        Forms\Components\ColorPicker::make('text_color')
                            ->label('Text Color')
                            ->default('#1E293B'),
                        Forms\Components\Toggle::make('dismissable')
                            ->label('Allow Users to Dismiss')
                            ->default(true),
                        Forms\Components\TextInput::make('url')
                            ->label('Link URL (optional)')
                            ->url()
                            ->maxLength(500)
                            ->helperText('Make the announcement bar clickable'),
                    ])->columns(2),
            ]);
    }
}
