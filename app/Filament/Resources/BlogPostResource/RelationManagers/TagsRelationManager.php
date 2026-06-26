<?php

namespace App\Filament\Resources\BlogPostResource\RelationManagers;

use App\Filament\Support\AdminUi;
use App\Models\BlogTag;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TagsRelationManager extends RelationManager
{
    protected static string $relationship = 'tags';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->label('Tag Name (JSON)')
                    ->helperText('JSON format: {"en": "...", "de": "...", "lt": "...", "fr": "...", "es": "..."}')
                    ->required()
                    ->maxLength(100)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, callable $set) {
                        if (is_string($state) && filled($state)) {
                            $set('slug', Str::slug($state));
                        }
                    }),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(200),
            ]);
    }

    public function table(Table $table): Table
    {
        return AdminUi::configureTable($table)->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Tag Name')
                    ->getStateUsing(fn ($record): string => is_array($record->name) ? ($record->name['en'] ?? '—') : ($record->name ?? '—'))
                    ->limit(25),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->limit(25),
            ])
            ->headerActions([
                Actions\AttachAction::make()
                    ->recordSelectOptionsQuery(fn ($query) => $query->orderBy('name->en'))
                    ->preloadRecordSelect()
                    ->form(function (Actions\AttachAction $action): array {
                        return [
                            $action->getRecordSelect(),
                            Forms\Components\TextInput::make('name')
                                ->label('Or create new tag')
                                ->placeholder('Enter new tag name...')
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if (filled($state)) {
                                        $set('slug', Str::slug($state));
                                    }
                                }),
                            Forms\Components\TextInput::make('slug')
                                ->label('Slug')
                                ->nullable(),
                        ];
                    })
                    ->mutateFormDataUsing(function (array $data): array {
                        if (!empty($data['name'])) {
                            $tag = BlogTag::firstOrCreate(
                                ['slug' => $data['slug'] ?? Str::slug($data['name'])],
                                ['name' => ['en' => $data['name']], 'slug' => $data['slug'] ?? Str::slug($data['name'])]
                            );

                            return ['recordId' => $tag->id];
                        }

                        return $data;
                    }),
                Actions\CreateAction::make(),
            ])
            ->actions([
                Actions\DetachAction::make(),
                Actions\EditAction::make(),
            ]);
    }
}
