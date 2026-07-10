<?php

namespace App\Filament\Resources\ShippingZoneResource\Pages;

use App\Filament\Resources\ShippingZoneResource;
use Filament\Actions;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewShippingZone extends ViewRecord
{
    protected static string $resource = ShippingZoneResource::class;

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
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 2])
                            ->schema([
                                Section::make('Zone Details')
                                    ->icon('heroicon-o-globe-alt')
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label('Zone Name'),
                                    ]),
                                Section::make('Coverage Snapshot')
                                    ->icon('heroicon-o-map')
                                    ->schema([
                                        TextEntry::make('countries_count')
                                            ->label('Countries')
                                            ->state(fn ($record): int => $record->countries()->count())
                                            ->badge()
                                            ->color('info')
                                            ->icon('heroicon-o-globe-alt'),
                                        TextEntry::make('methods_count')
                                            ->label('Methods')
                                            ->state(fn ($record): int => $record->methods()->count())
                                            ->badge()
                                            ->color('success')
                                            ->icon('heroicon-o-truck'),
                                    ])
                                    ->columns(2),
                            ]),
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Status')
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
                                            ->dateTime('M j, Y H:i'),
                                        TextEntry::make('updated_at')
                                            ->label('Updated')
                                            ->since(),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
