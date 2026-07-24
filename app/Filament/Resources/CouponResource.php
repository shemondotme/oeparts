<?php

namespace App\Filament\Resources;

use App\Enums\DiscountType;
use App\Filament\Resources\CouponResource\Pages;
use App\Filament\Resources\CouponResource\RelationManagers;
use App\Filament\Support\AdminUi;
use App\Models\Coupon;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static ?string $recordTitleAttribute = 'code';

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
                Grid::make(['default' => 1, 'xl' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 2])
                            ->schema([
                                Section::make('Coupon Details')
                                    ->description('Discount code customers enter at checkout.')
                                    ->icon('heroicon-o-tag')
                                    ->schema([
                                        Forms\Components\TextInput::make('code')
                                            ->label(__('admin.coupon_code'))
                                            ->placeholder('e.g. SUMMER2024, BRAKE10')
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(50)
                                            ->dehydrateStateUsing(fn ($state) => strtoupper((string) $state))
                                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                            ->helperText('Customers enter this code at checkout. Automatically converted to uppercase.'),
                                        Forms\Components\TextInput::make('name')
                                            ->label(__('admin.internal_name'))
                                            ->placeholder('e.g. Summer Sale 2024')
                                            ->required()
                                            ->maxLength(100)
                                            ->helperText('Descriptive name for internal reference. Not shown to customers.'),
                                        Forms\Components\Select::make('discount_type')
                                            ->label(__('admin.discount_type'))
                                            ->options(DiscountType::class)
                                            ->native(false)
                                            ->required()
                                            ->helperText('Percentage discount or fixed amount off the order total.'),
                                        Forms\Components\TextInput::make('discount_value')
                                            ->label(__('admin.discount_value'))
                                            ->numeric()
                                            ->required()
                                            ->minValue(0)
                                            ->placeholder('e.g. 10 or 25.00')
                                            ->helperText('The discount amount. For percentage: enter 10 for 10%. For fixed: enter the euro amount.'),
                                        Forms\Components\TextInput::make('min_order_amount')
                                            ->label(__('admin.minimum_order_amount_ex_vat'))
                                            ->numeric()
                                            ->prefix('€')
                                            ->minValue(0)
                                            ->placeholder('e.g. 50.00')
                                            ->helperText('Minimum order subtotal required to use this coupon. Leave empty for no minimum.'),
                                    ])
                                    ->columns(2),
                            ]),

                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Limits & Status')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->description('Usage limits, expiration, and active status for this coupon.')
                                    ->schema([
                                        Forms\Components\TextInput::make('usage_limit')
                                            ->label(__('admin.total_usage_limit'))
                                            ->numeric()
                                            ->minValue(0)
                                            ->placeholder('0')
                                            ->helperText('Maximum total times this coupon can be used. 0 = unlimited.'),
                                        Forms\Components\TextInput::make('usage_limit_per_user')
                                            ->label(__('admin.usage_limit_per_customer'))
                                            ->numeric()
                                            ->minValue(0)
                                            ->placeholder('0')
                                            ->helperText('Maximum times each customer can use this coupon. 0 = unlimited.'),
                                        Forms\Components\DateTimePicker::make('expires_at')
                                            ->label(__('admin.expiration_date'))
                                            ->nullable()
                                            ->helperText('Coupon expires at midnight on this date. Leave empty for no expiration.'),
                                        Forms\Components\Toggle::make('is_active')
                                            ->label(__('admin.coupon_active'))
                                            ->helperText('Inactive coupons cannot be applied at checkout.')
                                            ->default(true),
                                        Forms\Components\Select::make('user_id')
                                            ->label(__('admin.restrict_to_customer_optional'))
                                            ->relationship('user', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->helperText('Leave empty for a generic coupon anyone can redeem. Select a customer to issue a personal code only that account can use — e.g. a B2B bulk-discount code for one client.'),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->modifyQueryUsing(fn ($query) => $query->withCount('usages')->with('user'))
            ->columns([
            Tables\Columns\TextColumn::make('code')
                ->label(__('admin.coupon_code'))
                ->searchable()
                ->copyable()
                ->copyMessage('Coupon code copied')
                ->badge()
                ->color('warning')
                ->fontMono()
                ->sortable()
                ->weight(FontWeight::Medium),
            Tables\Columns\TextColumn::make('name')
                ->label(__('admin.name'))
                ->searchable()
                ->limit(30)
                ->weight(FontWeight::Medium),
            Tables\Columns\TextColumn::make('discount_type')
                ->label(__('admin.type'))
                ->badge()
                ->color(fn (DiscountType $state): string => match ($state) {
                    DiscountType::Percentage => 'warning',
                    DiscountType::Fixed => 'info',
                })
                ->icon(fn (DiscountType $state): string => match ($state) {
                    DiscountType::Percentage => 'heroicon-o-adjustments-horizontal',
                    DiscountType::Fixed => 'heroicon-o-currency-euro',
                }),
            Tables\Columns\TextColumn::make('discount_value')
                ->label(__('admin.value'))
                // A 10% coupon rendered as "€10.00" with unconditional money().
                ->getStateUsing(fn (Coupon $record): string => $record->discount_type === DiscountType::Percentage
                    ? rtrim(rtrim((string) $record->discount_value, '0'), '.') . '%'
                    : format_money($record->discount_value))
                ->alignEnd()
                ->fontMono()
                ->weight('bold'),
                Tables\Columns\TextColumn::make('usage_limit')
                    ->label(__('admin.max_uses'))
                    ->numeric()
                    ->fontMono()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('is_active')
                ->label(__('admin.status'))
                ->badge()
                // Derived status: an expired coupon must not read "Active"
                // (expires_at is a hidden-by-default column).
                ->getStateUsing(fn (Coupon $record): string => match (true) {
                    ! $record->is_active => 'Inactive',
                    $record->expires_at !== null && $record->expires_at->isPast() => 'Expired',
                    default => 'Active',
                })
                ->color(fn (string $state): string => match ($state) {
                    'Active' => 'success',
                    'Expired' => 'warning',
                    default => 'gray',
                })
                ->icon(fn (string $state): string => match ($state) {
                    'Active' => 'heroicon-o-check-circle',
                    'Expired' => 'heroicon-o-clock',
                    default => 'heroicon-o-x-circle',
                })
                ->alignCenter(),
            Tables\Columns\TextColumn::make('user.name')
                ->label(__('admin.restricted_to'))
                ->placeholder('Anyone')
                ->toggleable(),
            Tables\Columns\TextColumn::make('expires_at')
                ->label(__('admin.expires'))
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('created_at')
                ->label(__('admin.created'))
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('discount_type')
                    ->label(__('admin.discount_type'))
                    ->options(DiscountType::class)
                    ->helperText('Filter by percentage or fixed amount coupons.')
                    ->columnSpan(1),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('admin.coupon_status'))
                    ->placeholder('All')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only')
                    ->columnSpan(1),
                Tables\Filters\TernaryFilter::make('user_id')
                    ->label(__('admin.personal_coupons'))
                    ->placeholder('All')
                    ->trueLabel('Personal Only')
                    ->falseLabel('Generic Only')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('user_id'),
                        false: fn ($query) => $query->whereNull('user_id'),
                    )
                    ->columnSpan(1),
            ])
            ->filtersFormColumns(2)
            ->actions(AdminUi::recordActions())
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-tag')
            ->emptyStateHeading('No coupons created yet')
            ->emptyStateDescription('Create discount codes for promotions, campaigns, and customer retention.')
            ->emptyStateActions([
                Actions\Action::make('create')
                    ->label(__('admin.create_coupon'))
                    ->url(static::getUrl('create'))
                    ->icon('heroicon-o-plus')
                    ->button(),
            ])
        ->bulkActions([
            Actions\BulkActionGroup::make([
                AdminUi::exportCsvBulkAction('Export Coupons', [
                    'code' => 'Coupon Code',
                    'name' => 'Name',
                    'discount_type' => 'Type',
                    'discount_value' => 'Value',
                    'usage_limit' => 'Max Uses',
                    'is_active' => 'Active',
                    'expires_at' => 'Expires',
                    'created_at' => 'Created',
                ]),
                Actions\DeleteBulkAction::make(),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\UsagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'view' => Pages\ViewCoupon::route('/{record}'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'name'];
    }
}
