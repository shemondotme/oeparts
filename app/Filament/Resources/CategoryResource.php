<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-folder';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Catalog';
    }

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Category Details')
                    ->schema([
                        Forms\Components\TextInput::make('name.en')
                            ->label('Name (English)')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('name.de')
                            ->label('Name (German)')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('name.lt')
                            ->label('Name (Lithuanian)')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('name.fr')
                            ->label('Name (French)')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('name.es')
                            ->label('Name (Spanish)')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(200),
                        Forms\Components\Select::make('parent_id')
                            ->label('Parent Category')
                            ->relationship('parent', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => is_array($record->name) ? ($record->name['en'] ?? $record->name[array_key_first($record->name)] ?? '—') : ($record->name ?? '—'))
                            ->searchable(),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name.en')
                    ->label('Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('parent.name.en')
                    ->label('Parent'),
                Tables\Columns\TextColumn::make('blog_posts_count')
                    ->label('Posts')
                    ->counts('blogPosts'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
