<?php

namespace App\Filament\Resources\CouponResource\Pages;

use App\Filament\Resources\CouponResource;
use Filament\Actions;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewCoupon extends ViewRecord
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Coupon Details')
                    ->schema([
                        TextEntry::make('code')
                            ->extraAttributes(['class' => 'oem-number']),
                        TextEntry::make('name')
                            ->label('Admin Label'),
                        TextEntry::make('discount_type')
                            ->label('Type')
                            ->badge()
                            ->color(fn ($record): string => $record->discount_type->value === 'percentage' ? 'warning' : 'info')
                            ->getStateUsing(fn ($record): string => $record->discount_type->value === 'percentage' ? "{$record->discount_value}%" : "€{$record->discount_value}"),
                        TextEntry::make('min_order_amount')
                            ->label('Min Order')
                            ->getStateUsing(fn ($record): string => $record->min_order_amount ? format_money($record->min_order_amount) : '—'),
                        TextEntry::make('expires_at')
                            ->label('Expires')
                            ->dateTime('M j, Y H:i')
                            ->placeholder('No expiry'),
                        IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                    ])->columns(3),

                Section::make('Usage Stats')
                    ->schema([
                        TextEntry::make('usages_count')
                            ->label('Times Used')
                            ->getStateUsing(fn ($record): string => $record->usages()->count()),
                        TextEntry::make('usage_limit')
                            ->label('Usage Limit')
                            ->placeholder('Unlimited'),
                        TextEntry::make('usage_limit_per_user')
                            ->label('Limit Per User')
                            ->placeholder('Unlimited'),
                    ])->columns(3),
            ]);
    }
}
