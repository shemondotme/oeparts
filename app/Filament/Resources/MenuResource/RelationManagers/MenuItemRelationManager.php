<?php

namespace App\Filament\Resources\MenuResource\RelationManagers;

use App\Enums\MenuTarget;
use App\Models\Page;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MenuItemRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $recordTitleAttribute = 'label';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('label')
                    ->label('Label (JSON)')
                    ->helperText('e.g. {"en": "Home", "de": "Startseite"}')
                    ->required(),
                Forms\Components\Select::make('type')
                    ->options([
                        'url'  => 'Custom URL',
                        'page' => 'CMS Page',
                    ])
                    ->default('url')
                    ->reactive()
                    ->required(),
                Forms\Components\TextInput::make('url')
                    ->label('URL')
                    ->helperText('e.g. /en/contact or https://example.com')
                    ->maxLength(255)
                    ->hidden(fn ($get): bool => $get('type') !== 'url'),
                Forms\Components\Select::make('page_id')
                    ->label('CMS Page')
                    ->options(function () {
                        return Page::query()
                            ->where('status', \App\Enums\ContentStatus::Published)
                            ->get()
                            ->mapWithKeys(fn (Page $page): array => [
                                $page->id => is_array($page->title)
                                    ? ($page->title['en'] ?? $page->title[array_key_first($page->title)] ?? "Page #{$page->id}")
                                    : ($page->title ?? "Page #{$page->id}"),
                            ]);
                    })
                    ->searchable()
                    ->hidden(fn ($get): bool => $get('type') !== 'page'),
                Forms\Components\Select::make('parent_id')
                    ->label('Parent Item')
                    ->options(function (RelationManager $livewire): array {
                        $menu = $livewire->getOwnerRecord();
                        return $menu->items()
                            ->whereNull('parent_id')
                            ->get()
                            ->mapWithKeys(fn ($item): array => [
                                $item->id => is_array($item->label)
                                    ? ($item->label['en'] ?? $item->label[array_key_first($item->label)] ?? "Item #{$item->id}")
                                    : ($item->label ?? "Item #{$item->id}"),
                            ])
                            ->toArray();
                    })
                    ->nullable()
                    ->helperText('Leave empty for top-level item'),
                Forms\Components\Select::make('target')
                    ->options(MenuTarget::class)
                    ->default('_self')
                    ->required(),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('Label')
                    ->getStateUsing(fn ($record): string => is_array($record->label) ? ($record->label['en'] ?? $record->label[array_key_first($record->label)] ?? '—') : ($record->label ?? '—'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'page' ? 'success' : 'gray')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('url')
                    ->label('URL')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('parent_id')
                    ->label('Parent')
                    ->getStateUsing(function ($record): string {
                        if (!$record->parent) {
                            return '—';
                        }
                        $label = $record->parent->label;
                        return is_array($label) ? ($label['en'] ?? $label[array_key_first($label)] ?? '—') : ($label ?? '—');
                    })
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('target')
                    ->badge()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sort')
                    ->alignCenter()
                    ->sortable(),
            ])
            ->headerActions([
                Actions\CreateAction::make(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order')
            ->paginated(false);
    }
}
