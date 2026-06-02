<?php

namespace App\Filament\Resources;

use App\Enums\LoginUserType;
use App\Enums\LogStatus;
use App\Filament\Resources\LoginLogResource\Pages;
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
        return 'heroicon-o-shield-check';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
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
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (LoginUserType $state): string => match ($state) {
                        LoginUserType::Admin => 'warning',
                        LoginUserType::Customer => 'info',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (LogStatus $state): string => match ($state) {
                        LogStatus::Success => 'success',
                        LogStatus::Failed => 'danger',
                    }),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_type')
                    ->label('Type')
                    ->options(LoginUserType::class),
                Tables\Filters\SelectFilter::make('status')
                    ->options(LogStatus::class),
            ])
            ->actions([
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
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
            'index' => Pages\ListLoginLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', LogStatus::Failed)->whereDate('created_at', today())->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
