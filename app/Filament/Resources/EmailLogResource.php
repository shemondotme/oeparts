<?php

namespace App\Filament\Resources;

use App\Enums\EmailTemplate;
use App\Enums\LogStatus;
use App\Filament\Resources\EmailLogResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\EmailLog;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class EmailLogResource extends Resource
{
    protected static ?string $model = EmailLog::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-inbox-arrow-down';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Marketing';
    }

    public static function getNavigationSort(): ?int
    {
        return 55;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'subject';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Email Details')
                    ->description('Detailed information about this sent email including recipient, template, and status.')
                    ->schema([
                        Forms\Components\TextInput::make('to_email')
                            ->label('Recipient Email')
                            ->email()
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('subject')
                            ->label('Email Subject')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Select::make('template_type')
                            ->label('Email Template')
                            ->options(EmailTemplate::class)
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Select::make('status')
                            ->label('Delivery Status')
                            ->options(LogStatus::class)
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Textarea::make('error_message')
                            ->label('Error Details')
                            ->rows(3)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Error message if the email failed to send.'),
                        Forms\Components\TextInput::make('related_type')
                            ->label('Related Model')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('The model type this email is associated with (e.g. Order, Customer).'),
                        Forms\Components\TextInput::make('related_id')
                            ->label('Related Record ID')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\DateTimePicker::make('sent_at')
                            ->label('Sent At')
                            ->disabled()
                            ->dehydrated(false),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->columns([
            Tables\Columns\TextColumn::make('to_email')
                ->label('To')
                ->searchable()
                ->sortable()
                ->limit(30),
            Tables\Columns\TextColumn::make('subject')
                ->label('Subject')
                ->searchable()
                ->limit(40),
            Tables\Columns\TextColumn::make('template_type')
                ->label('Template')
                ->badge()
                ->color(fn (EmailTemplate $state): string => match ($state) {
                    EmailTemplate::OrderConfirmation => 'success',
                    EmailTemplate::OrderStatus => 'info',
                    EmailTemplate::OrderShipped => 'success',
                    EmailTemplate::Welcome => 'success',
                    EmailTemplate::Otp => 'warning',
                    EmailTemplate::RefundProcessed => 'info',
                    EmailTemplate::AbandonedCart => 'warning',
                    EmailTemplate::NewsletterConfirm => 'info',
                    EmailTemplate::PasswordReset => 'warning',
                    EmailTemplate::ContactReply => 'info',
                })
                ->toggleable(),
            Tables\Columns\TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->color(fn (LogStatus $state): string => match ($state) {
                    LogStatus::Success => 'success',
                    LogStatus::Failed  => 'danger',
                })
                ->icon(fn (LogStatus $state): string => match ($state) {
                    LogStatus::Success => 'heroicon-o-check-circle',
                    LogStatus::Failed  => 'heroicon-o-x-circle',
                }),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Sent')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Delivery Status')
                    ->options(LogStatus::class)
                    ->native(false)
                    ->helperText('Filter by successful or failed deliveries.')
                    ->columnSpan(1),
                Tables\Filters\SelectFilter::make('template_type')
                    ->label('Email Template')
                    ->options(EmailTemplate::class)
                    ->native(false)
                    ->helperText('Filter by the type of email sent.')
                    ->columnSpan(1),
            ])
            ->filtersFormColumns(2)
        ->actions([
            ...AdminUi::recordActionsReadOnly(),
        ])
        ->bulkActions([
            Actions\BulkActionGroup::make([
                AdminUi::exportCsvBulkAction('Export Email Logs', [
                    'to_email' => 'To',
                    'subject' => 'Subject',
                    'template_type' => 'Template',
                    'status' => 'Status',
                    'sent_at' => 'Sent At',
                ]),
                Actions\DeleteBulkAction::make(),
            ]),
        ])
            ->defaultSort('sent_at', 'desc')
            ->emptyStateIcon('heroicon-o-envelope')
            ->emptyStateHeading('No emails logged yet')
            ->emptyStateDescription('Email delivery logs will appear here once emails are sent through the system. Failed emails will be highlighted.');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailLogs::route('/'),
            'view'  => Pages\ViewEmailLog::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return \App\Support\NavBadge::count('emails_failed', fn () => static::getModel()::where('status', LogStatus::Failed)->count());
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
