<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AbandonedCartResource\Pages;
use App\Models\AbandonedCart;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AbandonedCartResource extends Resource
{
    protected static ?string $model = AbandonedCart::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-shopping-cart';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Marketing';
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cart Information')
                    ->schema([
                        Forms\Components\TextInput::make('guest_email')
                            ->label('Guest Email')
                            ->email(),
                        Forms\Components\Textarea::make('cart_snapshot')
                            ->label('Cart Snapshot')
                            ->json()
                            ->columnSpanFull(),
                        Forms\Components\DateTimePicker::make('last_active_at')
                            ->label('Last Active'),
                        Forms\Components\Toggle::make('recovery_email_sent')
                            ->label('Recovery Email Sent'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable(),
                Tables\Columns\TextColumn::make('guest_email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('last_active_at')
                    ->label('Last Active')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\IconColumn::make('recovery_email_sent')
                    ->label('Recovery Sent')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('last_active_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('recovery_email_sent')
                    ->label('Recovery Email Sent'),
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
            'index' => Pages\ListAbandonedCarts::route('/'),
        ];
    }
}
