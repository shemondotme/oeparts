<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class AboutLicenseSettings extends SettingsPage
{
    protected static ?string $title = 'About & License';

    protected static string $settingsGroup = 'about';

    protected static ?int $navigationSort = 100;

    public static function getNavigationLabel(): string
    {
        return 'About';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-information-circle';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Platform Information')
                    ->description('Display-only information about the OeParts platform installation.')
                    ->schema([
                        Forms\Components\TextInput::make('app_version')
                            ->label('Application Version')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(config('app.version', '1.0.0')),

                        Forms\Components\TextInput::make('laravel_version')
                            ->label('Laravel Version')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(app()->version()),

                        Forms\Components\TextInput::make('php_version')
                            ->label('PHP Version')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(PHP_VERSION),

                        Forms\Components\TextInput::make('mysql_version')
                            ->label('MySQL Version')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(\DB::select('SELECT VERSION() as ver')[0]->ver ?? 'Unknown'),
                    ])->columns(2),

                Section::make('License')
                    ->description('MIT License — open-source software.')
                    ->schema([
                        Forms\Components\Textarea::make('license_text')
                            ->label('License')
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(8)
                            ->columnSpanFull()
                            ->default('MIT License

Copyright (c) ' . date('Y') . ' OeParts

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.'),
                    ]),
            ]);
    }
}
