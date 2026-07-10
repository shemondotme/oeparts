<?php

namespace App\Filament\Resources;

use App\Enums\LoginUserType;
use App\Enums\LogStatus;
use App\Filament\Resources\LoginLogResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\LoginLog;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class LoginLogResource extends Resource
{
    protected static ?string $model = LoginLog::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-finger-print';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Administration';
    }

    public static function getNavigationSort(): ?int
    {
        return 100;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'email';
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->label('Email Address')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('user_type')
                    ->label('Account Type')
                    ->badge()
                    ->color(fn (LoginUserType $state): string => match ($state) {
                        LoginUserType::Admin => 'warning',
                        LoginUserType::Customer => 'info',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('Result')
                    ->badge()
                    ->color(fn (LogStatus $state): string => match ($state) {
                        LogStatus::Success => 'success',
                        LogStatus::Failed => 'danger',
                    }),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->fontMono()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Attempted At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_type')
                    ->label('Account Type')
                    ->options(LoginUserType::class)
                    ->native(false)
                    ->helperText('Filter by admin or customer login attempts.'),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Result')
                    ->options(LogStatus::class)
                    ->native(false)
                    ->helperText('Show successful or failed login attempts.'),
            ])
            ->actions([
                ...AdminUi::recordActionsReadOnly(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    AdminUi::exportCsvBulkAction('Export Login Logs', [
                        'email' => 'Email',
                        'user_type' => 'Account Type',
                        'status' => 'Result',
                        'ip_address' => 'IP Address',
                        'created_at' => 'Attempted At',
                    ]),
                    // No bulk delete: login attempts are the security audit
                    // trail (rows are read-only); retention is logs:clean's job.
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-finger-print')
            ->emptyStateHeading('No login attempts logged')
            ->emptyStateDescription('Login attempts will appear here, including both successful and failed authentication attempts.');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoginLogs::route('/'),
            'view'  => Pages\ViewLoginLog::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return \App\Support\NavBadge::count('logins_failed_today', fn () => static::getModel()::where('status', LogStatus::Failed)->where('created_at', '>=', now()->startOfDay())->count());
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['email'];
    }
}
