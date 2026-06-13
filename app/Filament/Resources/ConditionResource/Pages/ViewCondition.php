<?php

namespace App\Filament\Resources\ConditionResource\Pages;

use App\Filament\Resources\ConditionResource;
use App\Models\Condition;
use Filament\Actions;
use Filament\Infolists;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewCondition extends ViewRecord
{
    protected static string $resource = ConditionResource::class;

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
                                Section::make('Condition Details')
                                    ->icon('heroicon-o-tag')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('name')
                                            ->label('Name'),
                                        Infolists\Components\TextEntry::make('slug')
                                            ->label('Slug')
                                            ->badge()
                                            ->color('gray')
                                            ->copyable(),
                                        Infolists\Components\TextEntry::make('sort_order')
                                            ->label('Sort Order'),
                                        Infolists\Components\TextEntry::make('is_active')
                                            ->label('Active')
                                            ->badge()
                                            ->getStateUsing(fn ($record): string => $record->is_active ? 'Active' : 'Inactive')
                                            ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'gray')
                                            ->icon(fn (string $state): string => $state === 'Active' ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                                    ])->columns(2),
                            ]),

                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Badge Preview')
                                    ->icon('heroicon-o-swatch')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('bg_color')
                                            ->label('Background Color')
                                            ->badge()
                                            ->formatStateUsing(fn (string $state): string => $state)
                                            ->extraAttributes(fn (Condition $record): array => [
                                                'style' => "background-color: {$record->bg_color}; color: {$record->text_color}; font-family: monospace;",
                                            ]),
                                        Infolists\Components\TextEntry::make('text_color')
                                            ->label('Text Color')
                                            ->badge()
                                            ->formatStateUsing(fn (string $state): string => $state)
                                            ->extraAttributes(fn (Condition $record): array => [
                                                'style' => "background-color: {$record->text_color}; color: white; font-family: monospace;",
                                            ]),
                                        Infolists\Components\TextEntry::make('name')
                                            ->label('Live Preview')
                                            ->badge()
                                            ->formatStateUsing(fn (Condition $record): string => $record->name)
                                            ->extraAttributes(fn (Condition $record): array => [
                                                'style' => "background-color: {$record->bg_color} !important; color: {$record->text_color} !important; font-family: Geist Mono, monospace; font-weight: 700; font-size: 11px; letter-spacing: 0.22em; text-transform: uppercase;",
                                            ]),
                                    ]),
                            ]),
                    ]),

                Section::make('Usage Statistics')
                    ->icon('heroicon-o-chart-bar')
                    ->schema([
                        Infolists\Components\TextEntry::make('products_count')
                            ->label('Products Using This Condition')
                            ->counts('products')
                            ->badge()
                            ->color(fn (?int $state): string => $state && $state > 0 ? 'info' : 'gray'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime('M j, Y H:i'),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime('M j, Y H:i'),
                    ])->columns(3),
            ]);
    }
}
