<?php

namespace App\Filament\Resources\CarModelResource\Pages;

use App\Filament\Resources\CarModelResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class ViewCarModel extends ViewRecord
{
    protected static string $resource = CarModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function resolveRecord(string|int $key): Model
    {
        $record = parent::resolveRecord($key);
        $record->loadCount('products');

        return $record;
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
                                Section::make('Car Model Details')
                                    ->icon('heroicon-o-truck')
                                    ->description('General vehicle model definitions.')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('manufacturer.name')
                                            ->label('Manufacturer')
                                            ->getStateUsing(fn ($record): string => $record->manufacturer ? \App\Filament\Support\AdminUi::localizedName($record->manufacturer->name) : '—'),
                                        Infolists\Components\TextEntry::make('name')
                                            ->label('Model Name'),
                                        Infolists\Components\TextEntry::make('slug')
                                            ->label('URL Slug')
                                            ->copyable()
                                            ->columnSpanFull(),
                                    ])->columns(2),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Timeline & Status')
                                    ->icon('heroicon-o-calendar')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('year_from')
                                            ->label('Year From')
                                            ->placeholder('—'),
                                        Infolists\Components\TextEntry::make('year_to')
                                            ->label('Year To')
                                            ->placeholder('—'),
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
                                        Infolists\Components\TextEntry::make('products_count')
                                            ->label('Products')
                                            ->getStateUsing(fn ($record): string => (string) $record->products_count),
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
