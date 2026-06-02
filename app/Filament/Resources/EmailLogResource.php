<?php

namespace App\Filament\Resources;

use App\Enums\EmailTemplate;
use App\Enums\LogStatus;
use App\Filament\Resources\EmailLogResource\Pages;
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
        return 50;
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
                    ->schema([
                        Forms\Components\TextInput::make('to_email')
                            ->label('To')
                            ->email()
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('subject')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Select::make('template_type')
                            ->label('Template')
                            ->options(EmailTemplate::class)
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Select::make('status')
                            ->options(LogStatus::class)
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Textarea::make('error_message')
                            ->label('Error')
                            ->rows(3)
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('related_type')
                            ->label('Related')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('related_id')
                            ->label('Related ID')
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
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('to_email')
                    ->label('To')
                    ->searchable(),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('template_type')
                    ->label('Template')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (LogStatus $state): string => match ($state) {
                        LogStatus::Success => 'success',
                        LogStatus::Failed  => 'danger',
                    }),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Sent')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(LogStatus::class),
                Tables\Filters\SelectFilter::make('template_type')
                    ->label('Template')
                    ->options(EmailTemplate::class),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sent_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
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
        $count = static::getModel()::where('status', LogStatus::Failed)->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
