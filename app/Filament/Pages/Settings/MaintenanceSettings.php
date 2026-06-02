<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Artisan;

class MaintenanceSettings extends SettingsPage
{
    protected static ?string $title = 'Maintenance Mode';

    protected static string $settingsGroup = 'maintenance';

    protected static ?int $navigationSort = 23;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Maintenance Configuration')
                    ->schema([
                        Forms\Components\Toggle::make('enabled')
                            ->label('Enable Maintenance Mode')
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                if ($state) {
                                    Artisan::call('down', [
                                        '--retry' => $this->data['retry_after'] ?? 3600,
                                        '--allow' => array_filter(explode("\n", str_replace("\r\n", "\n", $this->data['allowed_ips'] ?? ''))),
                                    ]);
                                    Notification::make()
                                        ->title('Maintenance mode enabled')
                                        ->success()
                                        ->send();
                                } else {
                                    Artisan::call('up');
                                    Notification::make()
                                        ->title('Maintenance mode disabled')
                                        ->success()
                                        ->send();
                                }
                            })
                            ->default(false),
                        Forms\Components\Textarea::make('message')
                            ->label('Maintenance Message (Multilang JSON)')
                            ->helperText('JSON with keys: en, de, lt, fr, es')
                            ->rows(3)
                            ->columnSpanFull()
                            ->default(null),
                        Forms\Components\Textarea::make('allowed_ips')
                            ->label('Allowed IPs')
                            ->helperText('One IP per line — these IPs can access the site during maintenance')
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('estimated_back_at')
                            ->label('Estimated Back At')
                            ->helperText('e.g. 2025-04-01 14:00')
                            ->maxLength(50)
                            ->default(null),
                        Forms\Components\Toggle::make('show_estimated_time')
                            ->label('Show Estimated Return Time')
                            ->default(false),
                        Forms\Components\TextInput::make('contact_email')
                            ->label('Contact Email')
                            ->email()
                            ->maxLength(255)
                            ->default(null),
                    ])->columns(2),
            ]);
    }

    public function save(): void
    {
        $previousEnabled = settings('maintenance.enabled', false) === 'true';

        parent::save();

        $newEnabled = ($this->data['enabled'] ?? false) === true
            || ($this->data['enabled'] ?? 'false') === 'true';

        if ($previousEnabled !== $newEnabled) {
            if ($newEnabled) {
                Artisan::call('down', [
                    '--retry' => $this->data['retry_after'] ?? 3600,
                    '--allow' => array_filter(explode("\n", str_replace("\r\n", "\n", $this->data['allowed_ips'] ?? ''))),
                ]);
            } else {
                Artisan::call('up');
            }
        }
    }
}
