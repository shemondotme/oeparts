<?php

namespace App\Filament\Resources\SectionResource\Pages;

use App\Filament\Resources\SectionResource;
use App\Filament\Support\AdminUi;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewSection extends ViewRecord
{
    protected static string $resource = SectionResource::class;

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
                Grid::make(['default' => 1, 'xl' => 3])
                    ->columnSpanFull()
                    ->schema([
                        // ─── Main column ──────────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 2])
                            ->schema([
                                Section::make('Multilingual Titles')
                                    ->icon('heroicon-o-language')
                                    ->schema([
                                        KeyValueEntry::make('title')
                                            ->hiddenLabel()
                                            ->placeholder('No titles provided')
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Content Configuration (JSON)')
                                    ->icon('heroicon-o-code-bracket')
                                    ->schema([
                                        // KeyValueEntry fatals on the nested arrays real sections
                                        // carry — render pretty-printed JSON instead (safe for any shape).
                                        \Filament\Infolists\Components\TextEntry::make('content')
                                            ->hiddenLabel()
                                            // state() (not formatStateUsing) — Filament treats array
                                            // states as item lists and formats per element.
                                            ->state(fn ($record): string => filled($record->content)
                                                ? (json_encode($record->content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—')
                                                : 'No content configuration provided')
                                            ->fontMono()
                                            ->extraAttributes(['class' => 'whitespace-pre-wrap'])
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Section Settings')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->schema([
                                        TextEntry::make('type')
                                            ->label('Type')
                                            ->badge()
                                            ->color('gray')
                                            ->getStateUsing(fn ($record): string => ucwords(str_replace('_', ' ', $record->type))),
                                        TextEntry::make('location')
                                            ->label('Location')
                                            ->badge()
                                            ->color('info')
                                            ->formatStateUsing(fn ($state): string => ucfirst($state->value)),
                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->color(fn ($state): string => match ($state->value) {
                                                'published' => 'success',
                                                'draft'     => 'gray',
                                                'scheduled' => 'warning',
                                                'archived'  => 'danger',
                                                default     => 'gray',
                                            })
                                            ->formatStateUsing(fn ($state): string => ucfirst($state->value)),
                                        TextEntry::make('publish_at')
                                            ->label('Publish At')
                                            ->dateTime('M j, Y H:i')
                                            ->placeholder('—'),
                                        TextEntry::make('sort_order')
                                            ->label('Sort Order')
                                            ->numeric(),
                                        TextEntry::make('is_active')
                                            ->label('Active Status')
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

