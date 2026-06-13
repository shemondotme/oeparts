<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
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

class ViewPage extends ViewRecord
{
    protected static string $resource = PageResource::class;

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
                                Section::make('Multilingual Page Content')
                                    ->icon('heroicon-o-language')
                                    ->schema([
                                        KeyValueEntry::make('title')
                                            ->label('Titles')
                                            ->placeholder('No titles provided')
                                            ->columnSpanFull(),

                                        KeyValueEntry::make('content')
                                            ->label('Content Bodies (HTML)')
                                            ->placeholder('No contents provided')
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('SEO Metadata')
                                    ->icon('heroicon-o-globe-alt')
                                    ->collapsible()
                                    ->schema([
                                        KeyValueEntry::make('meta_title')
                                            ->label('Meta Titles')
                                            ->placeholder('—')
                                            ->columnSpanFull(),

                                        KeyValueEntry::make('meta_description')
                                            ->label('Meta Descriptions')
                                            ->placeholder('—')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Publishing & Visibility')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->schema([
                                        TextEntry::make('slug')
                                            ->label('Slug')
                                            ->copyable(),
                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->color(fn ($state): string => match ($state->value) {
                                                'published' => 'success',
                                                'draft'     => 'warning',
                                                'archived'  => 'danger',
                                                default     => 'gray',
                                            })
                                            ->formatStateUsing(fn ($state): string => ucfirst($state->value)),
                                        TextEntry::make('is_homepage')
                                            ->label('Homepage')
                                            ->badge()
                                            ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                                        TextEntry::make('is_header')
                                            ->label('Header Nav')
                                            ->badge()
                                            ->formatStateUsing(fn (bool $state): string => $state ? 'Visible' : 'Hidden')
                                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                                        TextEntry::make('is_footer')
                                            ->label('Footer Nav')
                                            ->badge()
                                            ->formatStateUsing(fn (bool $state): string => $state ? 'Visible' : 'Hidden')
                                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                                        TextEntry::make('published_at')
                                            ->label('Published At')
                                            ->dateTime('M j, Y H:i')
                                            ->placeholder('—'),
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

