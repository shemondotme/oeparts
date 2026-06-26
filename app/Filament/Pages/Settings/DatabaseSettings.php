<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as IlluminateSchema;

class DatabaseSettings extends SettingsPage
{
    protected static ?string $title = 'Database Info';

    protected static string $settingsGroup = 'database';

    protected static ?int $navigationSort = 101;

    public static function getNavigationLabel(): string
    {
        return 'Database';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-server-stack';
    }

    public function form(Schema $schema): Schema
    {
        $tables = [];
        if (IlluminateSchema::hasTable('settings')) {
            try {
                // SHOW TABLE STATUS is MySQL-specific syntax (the mandated
                // production driver per CLAUDE.md); guarded so any other
                // driver degrades to an empty table list instead of a fatal
                // query error.
                $tables = DB::select('SHOW TABLE STATUS');
            } catch (\Throwable $e) {
                $tables = [];
            }
        }

        return $schema
            ->components([
                Section::make('Connection Status')
                    ->description('Current database connection information.')
                    ->schema([
                        Forms\Components\TextInput::make('connection')
                            ->label('Driver')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(config('database.default')),

                        Forms\Components\TextInput::make('database_name')
                            ->label('Database Name')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(config('database.connections.' . config('database.default') . '.database')),

                        Forms\Components\TextInput::make('host')
                            ->label('Host')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(config('database.connections.' . config('database.default') . '.host')),
                    ])->columns(3),

                Section::make('Table Summary')
                    ->description('Overview of database tables and their row counts.')
                    ->schema([
                        Forms\Components\Placeholder::make('table_count')
                            ->label('Total Tables')
                            ->content(fn () => count($tables) . ' tables'),

                        Forms\Components\Placeholder::make('total_rows')
                            ->label('Total Rows')
                            ->content(fn () => number_format(collect($tables)->sum('Rows'))),

                        Forms\Components\Placeholder::make('total_size')
                            ->label('Total Size')
                            ->content(fn () => round(collect($tables)->sum('Data_length') / 1024 / 1024, 2) . ' MB'),
                    ])->columns(3),

                Section::make('Actions')
                    ->schema([
                        Forms\Components\Placeholder::make('optimize_hint')
                            ->label('Maintenance')
                            ->content('Run `php artisan db:optimize` from the CLI to optimize tables. Use Backup Dashboard for exports.'),
                    ]),
            ]);
    }
}
