<?php

namespace App\Filament\Resources\CarModelResource\Pages;

use App\Filament\Resources\CarModelResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewCarModel extends ViewRecord
{
    protected static string $resource = CarModelResource::class;

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
                Section::make('Car Model Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('manufacturer.name')
                            ->label('Manufacturer')
                            ->getStateUsing(fn ($record): string => $record->manufacturer ? (is_array($record->manufacturer->name) ? ($record->manufacturer->name['en'] ?? $record->manufacturer->name[array_key_first($record->manufacturer->name)] ?? '—') : ($record->manufacturer->name ?? '—')) : '—'),
                        Infolists\Components\TextEntry::make('name')
                            ->label('Model Name'),
                        Infolists\Components\TextEntry::make('slug'),
                        Infolists\Components\TextEntry::make('year_from')
                            ->label('Year From')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('year_to')
                            ->label('Year To')
                            ->placeholder('—'),
                        Infolists\Components\IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('sort_order')
                            ->label('Sort Order'),
                    ])->columns(3),
            ]);
    }
}
