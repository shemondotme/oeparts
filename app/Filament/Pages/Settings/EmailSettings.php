<?php

namespace App\Filament\Pages\Settings;

use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use App\Jobs\SendTestEmailJob;

class EmailSettings extends SettingsPage
{
    protected static ?string $title = 'Email Settings';

    protected static string $settingsGroup = 'email';

    protected static ?int $navigationSort = 14;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testEmail')
                ->label('Send Test Email')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Send Test Email')
                ->modalDescription('This will send a test email to the admin recipient using your current SMTP settings.')
                ->modalSubmitActionLabel('Send Now')
                ->action(function () {
                    $adminEmail = $this->data['admin_notify_email'] ?? $this->data['from_address'] ?? null;

                    if (! $adminEmail) {
                        Notification::make()
                            ->title('No recipient')
                            ->body('Please set an admin notification email or sender email first.')
                            ->warning()
                            ->send();

                        return;
                    }

                    try {
                        SendTestEmailJob::dispatch($adminEmail);

                        Notification::make()
                            ->title('Test email queued')
                            ->body("Queued for {$adminEmail}. Check your inbox shortly.")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Email failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('From Address Configuration')
                    ->description('Set general identities for outgoing emails.')
                    ->schema([
                        Forms\Components\TextInput::make('from_name')
                            ->label('Sender Name')
                            ->maxLength(255)
                            ->placeholder('OeParts Europe')
                            ->default('OeParts'),

                        Forms\Components\TextInput::make('from_address')
                            ->label('Sender Email (From)')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('noreply@oeparts.lt')
                            ->default(null),

                        Forms\Components\TextInput::make('reply_to')
                            ->label('Reply-To Email Address')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('info@oeparts.lt')
                            ->default(null),
                    ])->columns(2),

                Section::make('SMTP Server Details')
                    ->description('Configure SMTP mail service parameters. Credentials are saved encrypted at rest.')
                    ->schema([
                        Forms\Components\TextInput::make('smtp_host')
                            ->label('SMTP Server Host')
                            ->maxLength(255)
                            ->placeholder('smtp.mailgun.org')
                            ->default(null),

                        Forms\Components\TextInput::make('smtp_port')
                            ->label('SMTP Port')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->placeholder('587')
                            ->default(587),

                        Forms\Components\Select::make('smtp_encryption')
                            ->label('Connection Encryption')
                            ->options([
                                'tls' => 'TLS (Secure)',
                                'ssl' => 'SSL (Legacy)',
                                '' => 'None',
                            ])
                            ->default('tls'),

                        Forms\Components\TextInput::make('smtp_username')
                            ->label('SMTP Auth Username')
                            ->maxLength(255)
                            ->placeholder('postmaster@domain.com')
                            ->default(null),

                        Forms\Components\TextInput::make('smtp_password')
                            ->label('SMTP Auth Password')
                            ->password()
                            ->revealable()
                            ->helperText('Stored encrypted inside database')
                            ->default(null),
                    ])->columns(2),

                Section::make('Admin System Alerts')
                    ->description('Set up notifications for administrators when critical storefront events trigger.')
                    ->schema([
                        Forms\Components\Toggle::make('admin_notify_new_order')
                            ->label('Notify Admin on New Order')
                            ->helperText('Send warning email when a buyer completes a checkout')
                            ->default(true),

                        Forms\Components\Toggle::make('admin_notify_new_inquiry')
                            ->label('Notify Admin on New Parts Inquiry')
                            ->helperText('Send email when a client creates a custom quote inquiry')
                            ->default(true),

                        Forms\Components\TextInput::make('admin_notify_email')
                            ->label('Recipient Admin Email')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('admin@oeparts.lt')
                            ->helperText('Defaults to Site Email if left blank')
                            ->columnSpanFull()
                            ->default(null),
                    ])->columns(2),
            ]);
    }
}
