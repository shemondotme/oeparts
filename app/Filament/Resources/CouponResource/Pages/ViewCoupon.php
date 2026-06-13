<?php

namespace App\Filament\Resources\CouponResource\Pages;

use App\Filament\Resources\CouponResource;
use Filament\Actions;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewCoupon extends ViewRecord
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'xl' => 3])
                    ->columnSpanFull()
                    ->schema([
                        // ─── Main column ──────────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 2])
                            ->schema([
                                Section::make('Coupon Details')
                                    ->icon('heroicon-o-tag')
                                    ->description('Core coupon configuration and discount rules.')
                                    ->schema([
                                        TextEntry::make('code')
                                            ->label('Coupon Code')
                                            ->extraAttributes(['class' => 'oem-number'])
                                            ->copyable()
                                            ->weight('bold'),
                                        TextEntry::make('name')
                                            ->label('Admin Label'),
                                        TextEntry::make('discount_type')
                                            ->label('Type')
                                            ->badge()
                                            ->color(fn ($record): string => $record->discount_type->value === 'percentage' ? 'warning' : 'info')
                                            ->getStateUsing(fn ($record): string => $record->discount_type->value === 'percentage' ? "{$record->discount_value}%" : "€{$record->discount_value}"),
                                        TextEntry::make('min_order_amount')
                                            ->label('Minimum Order')
                                            ->getStateUsing(fn ($record): string => $record->min_order_amount ? format_money($record->min_order_amount) : 'No minimum')
                                            ->placeholder('—'),
                                        TextEntry::make('expires_at')
                                            ->label('Expires')
                                            ->dateTime('M j, Y H:i')
                                            ->placeholder('No expiry'),
                                    ])->columns(2),

                                Section::make('Usage Statistics')
                                    ->icon('heroicon-o-chart-bar')
                                    ->description('How many times this coupon has been redeemed.')
                                    ->schema([
                                        TextEntry::make('usages_count')
                                            ->label('Times Used')
                                            ->getStateUsing(fn ($record): string => (string) $record->usages()->count())
                                            ->weight('bold'),
                                        TextEntry::make('usage_limit')
                                            ->label('Total Usage Limit')
                                            ->placeholder('Unlimited'),
                                        TextEntry::make('usage_limit_per_user')
                                            ->label('Per User Limit')
                                            ->placeholder('Unlimited'),
                                    ])->columns(3),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Status & Visibility')
                                    ->icon('heroicon-o-eye')
                                    ->schema([
                                        TextEntry::make('is_active')
                                            ->label('Status')
                                            ->badge()
                                            ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                                            ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                                            ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive'),
                                    ]),
                                Section::make('Record')
                                    ->icon('heroicon-o-clock')
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->label('Created')
                                            ->dateTime('M j, Y H:i')
                                            ->placeholder('—'),
                                        TextEntry::make('updated_at')
                                            ->label('Last Updated')
                                            ->since()
                                            ->placeholder('—'),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
