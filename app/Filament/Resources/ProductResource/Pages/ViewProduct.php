<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

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
                Section::make('Product Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('oem_number')
                            ->label('OEM Number')
                            ->extraAttributes(['class' => 'oem-number']),
                        Infolists\Components\TextEntry::make('normalized_oem')
                            ->label('Normalized OEM')
                            ->extraAttributes(['class' => 'oem-number']),
                        Infolists\Components\TextEntry::make('manufacturer.name')
                            ->label('Manufacturer')
                            ->getStateUsing(fn ($record): string => $record->manufacturer ? (is_array($record->manufacturer->name) ? ($record->manufacturer->name['en'] ?? $record->manufacturer->name[array_key_first($record->manufacturer->name)] ?? '—') : ($record->manufacturer->name ?? '—')) : '—'),
                        Infolists\Components\TextEntry::make('condition')
                            ->label('Condition')
                            ->badge()
                            ->color(fn ($state): string => match ($state->value) {
                                'new'            => 'success',
                                'used_grade_a'   => 'info',
                                'used_grade_b'   => 'warning',
                                'used_grade_c'   => 'gray',
                                'remanufactured' => 'purple',
                                'aftermarket'    => 'danger',
                                'new_old_stock'  => 'teal',
                                default          => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('price')
                            ->label('Price (ex. VAT)')
                            ->getStateUsing(fn ($record): string => format_money($record->price))
                            ->size('xl')
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('is_in_stock')
                            ->label('Stock')
                            ->getStateUsing(fn ($record): string => $record->is_in_stock ? 'In Stock' : 'Out of Stock')
                            ->badge()
                            ->color(fn ($record): string => $record->is_in_stock ? 'success' : 'danger'),
                    ])->columns(3),

                Section::make('Inventory')
                    ->schema([
                        Infolists\Components\TextEntry::make('delivery_time')
                            ->label('Delivery Time')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('moq')
                            ->label('Minimum Order Quantity'),
                        Infolists\Components\TextEntry::make('is_active')
                            ->label('Active')
                            ->getStateUsing(fn ($record): string => $record->is_active ? 'Yes' : 'No')
                            ->badge()
                            ->color(fn ($record): string => $record->is_active ? 'success' : 'gray'),
                    ])->columns(3),

                Section::make('Names (Multilingual)')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('')
                            ->getStateUsing(fn ($record): string => $record->name ? json_encode($record->name, JSON_PRETTY_PRINT) : '—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Descriptions (Multilingual)')
                    ->schema([
                        Infolists\Components\TextEntry::make('description')
                            ->label('')
                            ->getStateUsing(fn ($record): string => $record->description ? json_encode($record->description, JSON_PRETTY_PRINT) : '—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Cross-References')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('crossReferences')
                            ->hiddenLabel()
                            ->schema([
                                Infolists\Components\TextEntry::make('cross_oem_number')
                                    ->label('Cross OEM')
                                    ->extraAttributes(['class' => 'oem-number']),
                            ])
                            ->columns(1),
                    ]),

                Section::make('Car Models')
                    ->schema([
                        Infolists\Components\TextEntry::make('carModels')
                            ->label('')
                            ->getStateUsing(fn ($record): string => $record->carModels->pluck('name')->join(', ') ?: '—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
