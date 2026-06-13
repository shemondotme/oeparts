<?php

namespace App\Filament\Resources\MenuResource\Pages;

use App\Filament\Resources\MenuResource;
use App\Filament\Support\AdminUi;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewMenu extends ViewRecord
{
    protected static string $resource = MenuResource::class;

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
                                Section::make('Menu Details')
                                    ->icon('heroicon-o-bars-3')
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label('Menu Name')
                                            ->weight(\Filament\Support\Enums\FontWeight::Medium),
                                        TextEntry::make('location')
                                            ->badge()
                                            ->color(fn ($state): string => match ($state->value) {
                                                'header' => 'info',
                                                'footer' => 'gray',
                                                default  => 'gray',
                                            })
                                            ->formatStateUsing(fn ($state): string => ucfirst($state->value)),
                                        TextEntry::make('lang')
                                            ->label('Language')
                                            ->badge()
                                            ->formatStateUsing(fn (string $state): string => AdminUi::LOCALES[$state] ?? strtoupper($state))
                                            ->color('gray'),
                                    ])
                                    ->columns(3),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Settings')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->schema([
                                        TextEntry::make('is_active')
                                            ->label('Status')
                                            ->badge()
                                            ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                                    ]),

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

