<?php

namespace App\Filament\Resources;

use App\Enums\DiscountType;
use App\Filament\Resources\CouponResource\Pages;
use App\Models\Coupon;
use Filament\Forms;
use Filament\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-tag';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Marketing';
    }

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Coupon Information')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Coupon Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->dehydrateStateUsing(fn ($state) => strtoupper($state))
                            ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\Select::make('discount_type')
                            ->label('Discount Type')
                            ->options(DiscountType::class)
                            ->required(),
                        Forms\Components\TextInput::make('discount_value')
                            ->label('Discount Value')
                            ->numeric()
                            ->required()
                            ->minValue(0),
                        Forms\Components\TextInput::make('min_order_amount')
                            ->label('Minimum Order Amount')
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\TextInput::make('usage_limit')
                            ->label('Usage Limit')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('0 = unlimited'),
                        Forms\Components\TextInput::make('usage_limit_per_user')
                            ->label('Usage Limit Per User')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('0 = unlimited'),
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expires At'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->copyable()
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('discount_type')
                    ->badge(),
                Tables\Columns\TextColumn::make('discount_value')
                    ->money('EUR'),
                Tables\Columns\TextColumn::make('usage_limit')
                    ->label('Max Uses')
                    ->numeric(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('discount_type')
                    ->options(DiscountType::class),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
