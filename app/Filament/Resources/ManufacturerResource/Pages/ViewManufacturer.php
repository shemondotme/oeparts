<?php

namespace App\Filament\Resources\ManufacturerResource\Pages;

use App\Filament\Resources\ManufacturerResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

class ViewManufacturer extends ViewRecord
{
    protected static string $resource = ManufacturerResource::class;

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
                                Section::make('Manufacturer Details')
                                    ->icon('heroicon-o-building-office-2')
                                    ->description('Brand identity and country of origin.')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('name')
                                            ->label('Name')
                                            ->getStateUsing(fn ($record): string => is_array($record->name) ? ($record->name['en'] ?? $record->name[array_key_first($record->name)] ?? '—') : ($record->name ?? '—')),
                                        Infolists\Components\TextEntry::make('slug')
                                            ->label('URL Slug')
                                            ->copyable(),
                                        Infolists\Components\TextEntry::make('country_code')
                                            ->label('Country')
                                            ->placeholder('—'),
                                    ])->columns(2),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Settings & Visibility')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('is_verified_oem')
                                            ->label('Verified OEM')
                                            ->badge()
                                            ->getStateUsing(fn ($record): string => $record->is_verified_oem ? 'Verified' : 'Standard')
                                            ->color(fn (string $state): string => $state === 'Verified' ? 'success' : 'gray')
                                            ->icon(fn (string $state): string => $state === 'Verified' ? 'heroicon-o-check-badge' : 'heroicon-o-minus'),
                                        Infolists\Components\TextEntry::make('is_active')
                                            ->label('Active Visibility')
                                            ->badge()
                                            ->getStateUsing(fn ($record): string => $record->is_active ? 'Active' : 'Inactive')
                                            ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'gray')
                                            ->icon(fn (string $state): string => $state === 'Active' ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                                        Infolists\Components\TextEntry::make('sort_order')
                                            ->label('Sort Priority')
                                            ->placeholder('0'),
                                    ]),
                                Section::make('Record')
                                    ->icon('heroicon-o-clock')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('created_at')
                                            ->label('Created')
                                            ->dateTime('M j, Y H:i')
                                            ->placeholder('—'),
                                        Infolists\Components\TextEntry::make('updated_at')
                                            ->label('Last Updated')
                                            ->since()
                                            ->placeholder('—'),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
