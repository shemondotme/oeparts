<?php

namespace App\Filament\Resources\BlogPostResource\Pages;

use App\Filament\Resources\BlogPostResource;
use Filament\Actions\EditAction;
use Filament\Infolists;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewBlogPost extends ViewRecord
{
    protected static string $resource = BlogPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Post Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('title')
                            ->label('Title')
                            ->getStateUsing(fn ($record): string => is_array($record->title) ? ($record->title['en'] ?? '—') : ($record->title ?? '—')),
                        Infolists\Components\TextEntry::make('slug'),
                        Infolists\Components\TextEntry::make('category.name')
                            ->label('Category')
                            ->getStateUsing(fn ($record): string => $record->category ? (is_array($record->category->name) ? ($record->category->name['en'] ?? '—') : ($record->category->name ?? '—')) : '—'),
                        Infolists\Components\TextEntry::make('author.name')
                            ->label('Author'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn ($state): string => match ($state->value) {
                                'published' => 'success',
                                'draft'     => 'gray',
                                'archived'  => 'danger',
                                default     => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('published_at')
                            ->label('Published')
                            ->dateTime('M j, Y H:i')
                            ->placeholder('—'),
                    ])->columns(3),

                Section::make('Content')
                    ->schema([
                        Infolists\Components\TextEntry::make('excerpt')
                            ->label('Excerpt')
                            ->getStateUsing(fn ($record): string => is_array($record->excerpt) ? json_encode($record->excerpt) : ($record->excerpt ?? '—'))
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('content')
                            ->label('Content')
                            ->getStateUsing(fn ($record): string => is_array($record->content) ? json_encode($record->content) : ($record->content ?? '—'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
