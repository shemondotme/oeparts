<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

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
                Section::make('Customer Information')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('email'),
                        TextEntry::make('phone')
                            ->placeholder('—'),
                        \Filament\Infolists\Components\IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                        TextEntry::make('preferred_locale')
                            ->label('Language')
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label('Registered')
                            ->dateTime('M j, Y H:i'),
                    ])->columns(3),
            ]);
    }
}
