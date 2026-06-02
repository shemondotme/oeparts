<?php

namespace App\Filament\Resources;

use App\Enums\ContentStatus;
use App\Filament\Resources\BlogPostResource\Pages;
use App\Models\BlogPost;
use Filament\Forms;
use Filament\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BlogPostResource extends Resource
{
    protected static ?string $model = BlogPost::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-newspaper';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Content';
    }

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Content')
                    ->schema([
                        Forms\Components\TextInput::make('title.en')
                            ->label('Title (English)')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('title.de')
                            ->label('Title (German)')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('title.lt')
                            ->label('Title (Lithuanian)')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('title.fr')
                            ->label('Title (French)')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('title.es')
                            ->label('Title (Spanish)')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(200),
                        Forms\Components\Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => is_array($record->name) ? ($record->name['en'] ?? $record->name[array_key_first($record->name)] ?? '—') : ($record->name ?? '—'))
                            ->searchable(),
                        Forms\Components\Select::make('tags')
                            ->label('Tags')
                            ->relationship('tags', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => is_array($record->name) ? ($record->name['en'] ?? $record->name[array_key_first($record->name)] ?? '—') : ($record->name ?? '—'))
                            ->multiple()
                            ->preload(),
                        Forms\Components\Textarea::make('excerpt.en')
                            ->label('Excerpt (English)')
                            ->rows(2),
                        Forms\Components\RichEditor::make('content.en')
                            ->label('Content (English)')
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Meta')
                    ->schema([
                        Forms\Components\TextInput::make('meta_title.en')
                            ->label('Meta Title (English)')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('meta_description.en')
                            ->label('Meta Description (English)')
                            ->rows(2)
                            ->maxLength(500),
                    ])->columns(2),

                Section::make('Publishing')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options(ContentStatus::class)
                            ->required(),
                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('Published At'),
                        Forms\Components\DatePicker::make('last_reviewed_at')
                            ->label('Last Reviewed'),
                        Forms\Components\Select::make('author_id')
                            ->label('Author')
                            ->relationship('author', 'name')
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title.en')
                    ->label('Title')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('category.name.en')
                    ->label('Category')
                    ->badge(),
                Tables\Columns\TextColumn::make('author.name')
                    ->label('Author'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (ContentStatus $state): string => $state === ContentStatus::Published ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(ContentStatus::class),
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => is_array($record->name) ? ($record->name['en'] ?? $record->name[array_key_first($record->name)] ?? '—') : ($record->name ?? '—')),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\ViewAction::make(),
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
            'index' => Pages\ListBlogPosts::route('/'),
            'create' => Pages\CreateBlogPost::route('/create'),
            'edit' => Pages\EditBlogPost::route('/{record}/edit'),
            'view' => Pages\ViewBlogPost::route('/{record}'),
        ];
    }
}
