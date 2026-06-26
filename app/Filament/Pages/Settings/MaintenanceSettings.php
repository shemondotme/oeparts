<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Support\AdminUi;
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
                    ->description('Take the store offline for updates or service migrations. Allowed IPs will bypass the restriction.')
                    ->schema([
                        Forms\Components\Toggle::make('enabled')
                            ->label('Enable Maintenance Mode')
                            ->helperText('Put the storefront offline. Visitors will see the maintenance message.')
                            ->default(false),

                        Forms\Components\TextInput::make('contact_email')
                            ->label('Emergency Support Email')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('admin@oeparts.lt')
                            ->helperText('Shown on the maintenance page for contact inquiries')
                            ->default(null),

                        AdminUi::translatableTabs('Maintenance Message', [
                            'message' => [
                                'label' => 'Message',
                                'type' => 'textarea',
                                'rows' => 2,
                                'placeholders' => [
                                    'en' => "We're performing scheduled maintenance. We'll be back shortly.",
                                    'de' => 'Wir führen planmäßige Wartungsarbeiten durch. Wir sind in Kürze wieder da.',
                                    'lt' => 'Atliekami profilaktiniai darbai. Netrukus grįšime.',
                                    'fr' => 'Nous effectuons une maintenance programmée. Nous serons de retour sous peu.',
                                    'es' => 'Estamos realizando tareas de mantenimiento programadas. Volveremos pronto.',
                                ],
                            ],
                        ]),

                        Forms\Components\Textarea::make('allowed_ips')
                            ->label('Bypass IP Whitelist')
                            ->placeholder("e.g. 192.168.1.1\n80.90.100.110")
                            ->helperText('One IP address per line. These IPs will retain full access to the site.')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('estimated_back_at')
                            ->label('Estimated Return Time')
                            ->placeholder('e.g. 2025-06-02 18:00')
                            ->helperText('Visible text indicating when service is expected to resume')
                            ->maxLength(50)
                            ->default(null),

                        Forms\Components\Toggle::make('show_estimated_time')
                            ->label('Show Countdown/Time')
                            ->helperText('Display the estimated return time to store visitors')
                            ->default(false),

                        Forms\Components\TextInput::make('retry_after')
                            ->label('HTTP Retry-After Header (Seconds)')
                            ->numeric()
                            ->minValue(1)
                            ->placeholder('3600')
                            ->helperText('Tells search crawlers when to retry indexing (default 3600s / 1 hour)')
                            ->default(3600),
                    ])->columns(2),
            ]);
    }

    protected function afterSave(): void
    {
        $previousEnabled = settings('maintenance.enabled', false) === 'true';

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
