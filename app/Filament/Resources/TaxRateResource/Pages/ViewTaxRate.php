<?php

namespace App\Filament\Resources\TaxRateResource\Pages;

use App\Filament\Resources\TaxRateResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewTaxRate extends ViewRecord
{
    protected static string $resource = TaxRateResource::class;

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
                Section::make('Tax Rate Details')
                    ->icon('heroicon-o-receipt-percent')
                    ->schema([
                        Infolists\Components\TextEntry::make('country_code')
                            ->label('Code')
                            ->badge()
                            ->color('gray'),
                        Infolists\Components\TextEntry::make('country_name')
                            ->label('Country'),
                        Infolists\Components\TextEntry::make('rate')
                            ->label('Rate')
                            ->formatStateUsing(fn ($state): string => number_format((float) $state, 2).'%'),
                        Infolists\Components\TextEntry::make('is_active')
                            ->label('Status')
                            ->badge()
                            ->getStateUsing(fn ($record): string => $record->is_active ? 'Active' : 'Inactive')
                            ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'gray'),
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime('M j, Y H:i'),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime('M j, Y H:i'),
                    ])->columns(2),
            ]);
    }
}
