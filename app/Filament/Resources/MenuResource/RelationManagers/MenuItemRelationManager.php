<?php

namespace App\Filament\Resources\MenuResource\RelationManagers;

use App\Enums\MenuTarget;
use App\Models\MenuItem;
use App\Models\Page;
use App\Filament\Support\AdminUi;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Enums\FontWeight;

class MenuItemRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                AdminUi::translatableTabs('Locales', [
                    'label' => [
                        'label' => 'Label',
                        'required' => true,
                    ],
                ]),
                Forms\Components\Select::make('type')
                    ->options([
                        'url'  => 'Custom URL',
                        'page' => 'CMS Page',
                    ])
                    ->default('url')
                    ->reactive()
                    ->native(false)
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
                                $page->id => AdminUi::localizedName($page->title),
                            ]);
                    })
                    ->searchable()
                    ->native(false)
                    ->hidden(fn ($get): bool => $get('type') !== 'page'),
                Forms\Components\Select::make('parent_id')
                    ->label('Parent Item')
                    // Excludes the record being edited from its own options —
                    // without this, a top-level item could be set as its own
                    // parent, creating an immediate self-reference cycle.
                    ->options(function (RelationManager $livewire, ?MenuItem $record): array {
                        $menu = $livewire->getOwnerRecord();
                        return $menu->items()
                            ->whereNull('parent_id')
                            ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                            ->get()
                            ->mapWithKeys(fn ($item): array => [
                                $item->id => AdminUi::localizedName($item->label),
                            ])
                            ->toArray();
                    })
                    ->nullable()
                    ->native(false)
                    ->helperText('Leave empty for top-level item'),
                Forms\Components\Select::make('target')
                    ->options(MenuTarget::class)
                    ->default('_self')
                    ->native(false)
                    ->required(),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
            ]);
    }

    public function table(Table $table): Table
    {
        // Intentionally does not call AdminUi::configureTable() — this is a
        // small, non-paginated, drag-reorderable list (->paginated(false)
        // + ->reorderable('sort_order') below), a deliberately different UI
        // pattern from the persisted-filter/sort/search paginated tables
        // every other resource/relation manager uses.
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('parent'))
            // ->recordTitleAttribute('label') crashed with a TypeError
            // (Table::getRecordTitle() declares : string, but 'label' is a
            // translatable/array-cast column) the moment any action needing
            // a record title resolved — confirmed live via the Edit action,
            // a real 500 on every attempt. label is a JSON column, not a
            // plain string, so it needs the closure form + localization.
            ->recordTitle(fn ($record): string => AdminUi::localizedName($record->label))
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('Label')
                    ->getStateUsing(fn ($record): string => AdminUi::localizedName($record->label))
                    ->weight(FontWeight::Medium)
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
                    ->getStateUsing(fn ($record): string => $record->parent ? AdminUi::localizedName($record->parent->label) : '—')
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
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order')
            ->paginated(false);
    }
}

