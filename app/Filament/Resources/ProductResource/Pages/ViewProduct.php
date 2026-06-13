<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Condition;
use Filament\Actions;
use Filament\Infolists;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

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
                                Section::make('Identification')
                                    ->icon('heroicon-o-identification')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('oem_number')
                                            ->label('OEM Number')
                                            ->copyable()
                                            ->extraAttributes(['class' => 'oem-number']),
                                        Infolists\Components\TextEntry::make('normalized_oem')
                                            ->label('Normalized OEM')
                                            ->placeholder('—')
                                            ->extraAttributes(['class' => 'oem-number']),
                                        Infolists\Components\TextEntry::make('manufacturer.name')
                                            ->label('Manufacturer')
                                            ->getStateUsing(fn ($record): string => $record->manufacturer ? ProductResource::localizedName($record->manufacturer->name) : '—'),
                                        Infolists\Components\TextEntry::make('condition.name')
                                            ->label('Condition')
                                            ->badge()
                                            ->formatStateUsing(fn (?Condition $state): string => $state?->name ?? '—')
                                            ->extraAttributes(fn ($record): array => $record->condition ? [
                                                'style' => "background-color: {$record->condition->bg_color} !important; color: {$record->condition->text_color} !important;",
                                            ] : []),
                                    ])
                                    ->columns(2),

                                Section::make('Product Names')
                                    ->icon('heroicon-o-language')
                                    ->schema([
                                        Infolists\Components\KeyValueEntry::make('name')
                                            ->hiddenLabel()
                                            ->keyLabel('Language')
                                            ->valueLabel('Name')
                                            ->placeholder('No names provided')
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Descriptions')
                                    ->icon('heroicon-o-document-text')
                                    ->collapsible()
                                    ->schema([
                                        Infolists\Components\KeyValueEntry::make('description')
                                            ->hiddenLabel()
                                            ->keyLabel('Language')
                                            ->valueLabel('Description')
                                            ->placeholder('No descriptions provided')
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Compatibility & Cross-References')
                                    ->icon('heroicon-o-wrench-screwdriver')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('carModels')
                                            ->label('Compatible Car Models')
                                            ->getStateUsing(fn ($record): string => $record->carModels->pluck('name')->join(', ') ?: '—')
                                            ->columnSpanFull(),
                                        Infolists\Components\RepeatableEntry::make('crossReferences')
                                            ->label('Cross-References')
                                            ->placeholder('None')
                                            ->schema([
                                                Infolists\Components\TextEntry::make('cross_oem_number')
                                                    ->hiddenLabel()
                                                    ->extraAttributes(['class' => 'oem-number']),
                                            ])
                                            ->columns(1)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Pricing')
                                    ->icon('heroicon-o-currency-euro')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('price')
                                            ->label('Price (ex. VAT)')
                                            ->getStateUsing(fn ($record): string => format_money($record->price))
                                            ->size('xl')
                                            ->weight('bold')
                                            ->color('primary'),
                                    ]),

                                Section::make('Availability')
                                    ->icon('heroicon-o-cube')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('is_in_stock')
                                            ->label('Stock')
                                            ->getStateUsing(fn ($record): string => $record->is_in_stock ? 'In Stock' : 'Out of Stock')
                                            ->badge()
                                            ->color(fn ($record): string => $record->is_in_stock ? 'success' : 'danger')
                                            ->icon(fn ($record): string => $record->is_in_stock ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                                        Infolists\Components\TextEntry::make('is_active')
                                            ->label('Active on storefront')
                                            ->badge()
                                            ->getStateUsing(fn ($record): string => $record->is_active ? 'Active' : 'Inactive')
                                            ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'gray')
                                            ->icon(fn (string $state): string => $state === 'Active' ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                                        Infolists\Components\TextEntry::make('moq')
                                            ->label('Min. Order Quantity'),
                                        Infolists\Components\TextEntry::make('delivery_time')
                                            ->label('Delivery Time')
                                            ->placeholder('—'),
                                    ]),

                                Section::make('Record')
                                    ->icon('heroicon-o-clock')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('created_at')
                                            ->label('Created')
                                            ->dateTime('M j, Y g:i A')
                                            ->placeholder('—'),
                                        Infolists\Components\TextEntry::make('updated_at')
                                            ->label('Last updated')
                                            ->since()
                                            ->placeholder('—'),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
