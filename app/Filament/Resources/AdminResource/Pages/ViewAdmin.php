<?php

namespace App\Filament\Resources\AdminResource\Pages;

use App\Filament\Resources\AdminResource;
use Filament\Actions;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewAdmin extends ViewRecord
{
    protected static string $resource = AdminResource::class;

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
                Section::make('Admin Information')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('email'),
                        IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                        TextEntry::make('roles.name')
                            ->label('Roles')
                            ->badge()
                            ->color('primary'),
                        TextEntry::make('last_login_at')
                            ->label('Last Login')
                            ->since()
                            ->placeholder('Never'),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime('M j, Y H:i'),
                    ])->columns(3),
            ]);
    }
}
