<?php

namespace App\Filament\Resources\SectionResource\Pages;

use App\Filament\Resources\SectionResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Resources\Pages\ViewRecord;
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
                Section::make('Section Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('type')
                            ->label('Type')
                            ->getStateUsing(fn ($record): string => ucwords(str_replace('_', ' ', $record->type))),
                        Infolists\Components\TextEntry::make('location')
                            ->label('Location'),
                        Infolists\Components\TextEntry::make('title')
                            ->label('Title')
                            ->getStateUsing(fn ($record): string => is_array($record->title) ? json_encode($record->title) : ($record->title ?? '—')),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn ($state): string => match ($state->value) {
                                'published' => 'success',
                                'draft'     => 'gray',
                                'scheduled' => 'warning',
                                'archived'  => 'danger',
                                default     => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('publish_at')
                            ->label('Publish At')
                            ->dateTime('M j, Y H:i')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('sort_order')
                            ->label('Sort Order'),
                        Infolists\Components\IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                    ])->columns(3),

                Section::make('Content (JSON)')
                    ->schema([
                        Infolists\Components\TextEntry::make('content')
                            ->label('')
                            ->getStateUsing(fn ($record): string => $record->content ? json_encode($record->content, JSON_PRETTY_PRINT) : '—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
