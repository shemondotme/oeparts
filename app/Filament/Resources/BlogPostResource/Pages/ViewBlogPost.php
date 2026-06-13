<?php

namespace App\Filament\Resources\BlogPostResource\Pages;

use App\Filament\Resources\BlogPostResource;
use App\Filament\Support\AdminUi;
use Filament\Actions\EditAction;
use Filament\Infolists;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
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
                Grid::make(['default' => 1, 'xl' => 3])
                    ->columnSpanFull()
                    ->schema([
                        // ─── Main column ──────────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 2])
                            ->schema([
                                Section::make('Multilingual Post Content')
                                    ->icon('heroicon-o-language')
                                    ->schema([
                                        KeyValueEntry::make('title')
                                            ->label('Titles')
                                            ->placeholder('No titles provided')
                                            ->columnSpanFull(),

                                        KeyValueEntry::make('excerpt')
                                            ->label('Excerpts')
                                            ->placeholder('No excerpts provided')
                                            ->columnSpanFull(),

                                        KeyValueEntry::make('content')
                                            ->label('Content Bodies (HTML)')
                                            ->placeholder('No content bodies provided')
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
                                Section::make('Post Settings')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->schema([
                                        TextEntry::make('slug')
                                            ->label('Slug')
                                            ->copyable(),
                                        TextEntry::make('category.name')
                                            ->label('Category')
                                            ->getStateUsing(fn ($record): string => $record->category ? AdminUi::localizedName($record->category->name) : '—')
                                            ->badge()
                                            ->color('gray'),
                                        TextEntry::make('author.name')
                                            ->label('Author'),
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
                                        TextEntry::make('published_at')
                                            ->label('Published At')
                                            ->dateTime('M j, Y H:i')
                                            ->placeholder('—'),
                                        TextEntry::make('last_reviewed_at')
                                            ->label('Last Reviewed')
                                            ->date('M j, Y')
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

