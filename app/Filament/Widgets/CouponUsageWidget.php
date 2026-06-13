<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CouponResource;
use App\Models\Coupon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class CouponUsageWidget extends TableWidget
{
    public function getDescription(): ?string
    {
        return 'Most used discount codes';
    }

    use \App\Filament\Widgets\Concerns\HasDashboardPeriod;
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;

    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = -20;

    protected static ?string $heading = 'Top Coupons';

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Coupon::query()
                    ->withCount(['usages' => fn ($query) => $query->where('created_at', '>=', $this->periodStart())])
                    ->orderByDesc('usages_count')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->fontMono()
                    ->size('sm'),
                Tables\Columns\TextColumn::make('usages_count')
                    ->label('Uses')
                    ->numeric()
                    ->fontMono()
                    ->size('sm'),
                Tables\Columns\TextColumn::make('discount_value')
                    ->label('Value')
                    ->formatStateUsing(function ($state, Coupon $record): string {
                        $type = $record->discount_type instanceof \App\Enums\DiscountType
                            ? $record->discount_type->value
                            : $record->discount_type;

                        return $type === \App\Enums\DiscountType::Percentage->value
                            ? $state . '%'
                            : format_money($state);
                    })
                    ->fontMono()
                    ->size('sm'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (Coupon $record): string => CouponResource::getUrl('view', ['record' => $record]))
                    ->size('sm')
                    ->icon('heroicon-m-eye'),
            ])
            ->paginated(false);
    }
}
