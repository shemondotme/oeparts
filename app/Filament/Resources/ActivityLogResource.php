<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use App\Models\ActivityLog;
use Filament\Resources\Resource;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    protected static ?int $navigationSort = 30;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('admin.name')
                    ->label('Admin')
                    ->searchable(),
                Tables\Columns\TextColumn::make('action')
                    ->searchable()
                    ->badge(),
                Tables\Columns\TextColumn::make('model_type')
                    ->label('Model')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('model_id')
                    ->label('Record ID')
                    ->numeric(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                        'login' => 'Login',
                        'logout' => 'Logout',
                    ])
                    ->searchable(),
                Tables\Filters\SelectFilter::make('admin_id')
                    ->label('Admin')
                    ->relationship('admin', 'name'),
            ])
            ->actions([
                Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
            'view' => Pages\ViewActivityLog::route('/{record}'),
        ];
    }
}
