<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewCategory extends ViewRecord
{
    protected static string $resource = CategoryResource::class;

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
                                Section::make('Category Details')
                                    ->icon('heroicon-o-folder')
                                    ->schema([
                                        KeyValueEntry::make('name')
                                            ->label('Names')
                                            ->hiddenLabel()
                                            ->keyLabel('Language')
                                            ->valueLabel('Name')
                                            ->placeholder('No names provided')
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Organization')
                                    ->icon('heroicon-o-folder')
                                    ->schema([
                                        TextEntry::make('slug')
                                            ->label('URL Slug')
                                            ->copyable()
                                            ->extraAttributes(['class' => 'font-mono']),
                                        TextEntry::make('parent.name')
                                            ->label('Parent Category')
                                            ->getStateUsing(fn ($record): string => $record->parent ? \App\Filament\Support\AdminUi::localizedName($record->parent->name) : '—'),
                                        TextEntry::make('sort_order')
                                            ->label('Sort Order'),
                                    ])
                                    ->columns(2),
                            ]),
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Record')
                                    ->icon('heroicon-o-clock')
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->label('Created')
                                            ->dateTime('M j, Y H:i'),
                                        TextEntry::make('updated_at')
                                            ->label('Last updated')
                                            ->since(),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
