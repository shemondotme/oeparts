<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

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
                                Section::make('Contact Details')
                                    ->icon('heroicon-o-user')
                                    ->description('Primary contact information for this customer account.')
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label('Full Name')
                                            ->weight('bold'),
                                        TextEntry::make('email')
                                            ->label('Email Address')
                                            ->copyable()
                                            ->color('primary'),
                                        TextEntry::make('phone')
                                            ->label('Phone Number')
                                            ->copyable()
                                            ->placeholder('—'),
                                        TextEntry::make('preferred_locale')
                                            ->label('Preferred Language')
                                            ->badge()
                                            ->placeholder('—'),
                                        TextEntry::make('timezone')
                                            ->label('Timezone')
                                            ->placeholder('—'),
                                    ])->columns(2),
                                Section::make('Order Summary')
                                    ->icon('heroicon-o-shopping-bag')
                                    ->description('Aggregated order statistics from the customer\'s purchase history.')
                                    ->schema([
                                        TextEntry::make('orders_count')
                                            ->label('Total Orders')
                                            ->getStateUsing(fn ($record): string => (string) ($record->orders_count ?? 0)),
                                        TextEntry::make('orders_sum_grand_total')
                                            ->label('Total Spent')
                                            ->getStateUsing(fn ($record): string => format_money($record->orders_sum_grand_total ?? 0))
                                            ->weight('bold')
                                            ->color('success'),
                                        TextEntry::make('orders_avg_grand_total')
                                            ->label('Average Order Value')
                                            ->getStateUsing(fn ($record): string => ($record->orders_avg_grand_total ?? 0) > 0 ? format_money($record->orders_avg_grand_total) : '—'),
                                    ])->columns(3),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Account Status')
                                    ->icon('heroicon-o-shield-check')
                                    ->schema([
                                        TextEntry::make('is_active')
                                            ->label('Active')
                                            ->badge()
                                            ->getStateUsing(fn ($record): string => $record->is_active ? 'Active' : 'Inactive')
                                            ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'gray')
                                            ->icon(fn (string $state): string => $state === 'Active' ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                                        TextEntry::make('last_login_at')
                                            ->label('Last Login')
                                            ->since()
                                            ->placeholder('Never logged in'),
                                        TextEntry::make('last_order_date')
                                            ->label('Last Order')
                                            ->getStateUsing(fn ($record): ?string => $record->orders->first()?->created_at?->diffForHumans())
                                            ->placeholder('No orders'),
                                    ]),
                                Section::make('Record')
                                    ->icon('heroicon-o-clock')
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->label('Registered')
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
