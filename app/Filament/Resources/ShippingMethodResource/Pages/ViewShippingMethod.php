<?php

namespace App\Filament\Resources\ShippingMethodResource\Pages;

use App\Filament\Resources\ShippingMethodResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewShippingMethod extends ViewRecord
{
    protected static string $resource = ShippingMethodResource::class;

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
                                Section::make('Method details')
                                    ->icon('heroicon-o-truck')
                                    ->schema([
                                        TextEntry::make('zone.name')
                                            ->label('Shipping Zone'),
                                        TextEntry::make('flat_rate')
                                            ->label('Flat Rate')
                                            ->getStateUsing(fn ($record): string => format_money($record->flat_rate))
                                            ->extraAttributes(['class' => 'op-ship-rate']),
                                        TextEntry::make('free_shipping_threshold')
                                            ->label('Free Shipping Threshold')
                                            ->getStateUsing(fn ($record): string => filled($record->free_shipping_threshold)
                                                ? format_money($record->free_shipping_threshold)
                                                : '—'),
                                        TextEntry::make('estimated_days')
                                            ->label('Estimated Delivery')
                                            ->getStateUsing(fn ($record): string => $record->estimated_days_min && $record->estimated_days_max
                                                ? "{$record->estimated_days_min}–{$record->estimated_days_max} days"
                                                : '—')
                                            ->badge()
                                            ->color('gray'),
                                    ])
                                    ->columns(2),

                                Section::make('Name (by language)')
                                    ->icon('heroicon-o-language')
                                    ->schema([
                                        KeyValueEntry::make('name')
                                            ->hiddenLabel()
                                            ->keyLabel('Language')
                                            ->valueLabel('Method Name')
                                            ->placeholder('No names provided')
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Description (by language)')
                                    ->icon('heroicon-o-document-text')
                                    ->collapsible()
                                    ->schema([
                                        KeyValueEntry::make('description')
                                            ->hiddenLabel()
                                            ->keyLabel('Language')
                                            ->valueLabel('Description')
                                            ->placeholder('No descriptions provided')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Status & ordering')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->schema([
                                        TextEntry::make('is_active')
                                            ->label('Status')
                                            ->badge()
                                            ->getStateUsing(fn ($record): string => $record->is_active ? 'Active' : 'Inactive')
                                            ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'gray')
                                            ->icon(fn (string $state): string => $state === 'Active' ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                                        TextEntry::make('sort_order')
                                            ->label('Sort Order'),
                                    ]),

                                Section::make('Record')
                                    ->icon('heroicon-o-clock')
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->label('Created')
                                            ->dateTime('M j, Y H:i')
                                            ->placeholder('—'),
                                        TextEntry::make('updated_at')
                                            ->label('Last updated')
                                            ->since()
                                            ->placeholder('—'),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}

