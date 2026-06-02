<?php

namespace App\Filament\Resources\ManufacturerResource\Pages;

use App\Filament\Resources\ManufacturerResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewManufacturer extends ViewRecord
{
    protected static string $resource = ManufacturerResource::class;

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
                Section::make('Manufacturer Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('Name')
                            ->getStateUsing(fn ($record): string => is_array($record->name) ? ($record->name['en'] ?? $record->name[array_key_first($record->name)] ?? '—') : ($record->name ?? '—')),
                        Infolists\Components\TextEntry::make('slug'),
                        Infolists\Components\TextEntry::make('country_code')
                            ->label('Country'),
                        Infolists\Components\IconEntry::make('is_verified_oem')
                            ->label('Verified OEM')
                            ->boolean(),
                        Infolists\Components\IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('sort_order')
                            ->label('Sort Order'),
                    ])->columns(3),
            ]);
    }
}
