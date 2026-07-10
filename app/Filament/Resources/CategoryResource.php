<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\Category;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Notifications\NotificationAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    // `name` is a JSON multilang array — a raw record-title attribute makes
    // getRecordTitle() fatal (must return string). Resolved in the override.
    protected static ?string $recordTitleAttribute = null;

    public static function getRecordTitle(?\Illuminate\Database\Eloquent\Model $record): ?string
    {
        if (! $record instanceof Category) {
            return null;
        }

        return AdminUi::localizedName($record->name);
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-folder';
    }

    // Categories are a blog/content taxonomy (products never reference them)
    // — they live in the Content cluster with the rest of the CMS, not Catalog.
    protected static ?string $cluster = \App\Filament\Clusters\Content::class;

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'xl' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 2])
                            ->schema([
                                Section::make('Category Names')
                                    ->description('Multilingual names displayed on the blog and category pages.')
                                    ->icon('heroicon-o-language')
                                    ->schema([
                                        AdminUi::translatableTabs('Locales', [
                                            'name' => [
                                                'label' => 'Category Name',
                                                'placeholder' => 'e.g. Brake Parts, Engine, Suspension',
                                                'required' => true,
                                                'helperText' => 'English name is required and used as the default fallback.',
                                                'slugSync' => true,
                                            ],
                                        ], slugSyncTarget: 'slug'),
                                    ]),
                            ]),

                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Organization')
                                    ->icon('heroicon-o-folder')
                                    ->description('Category hierarchy, URL structure, and display ordering.')
                                    ->schema([
                                        Forms\Components\TextInput::make('slug')
                                            ->label('URL Slug')
                                            ->placeholder('e.g. brake-parts')
                                            ->helperText('Used in category page URLs (e.g. /blog/category/brake-parts).')
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(200),
                                        Forms\Components\Select::make('parent_id')
                                            ->label('Parent Category')
                                            ->relationship('parent', 'name')
                                            ->getOptionLabelFromRecordUsing(fn ($record) => AdminUi::localizedName($record->name))
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->helperText('Leave empty for top-level categories. Select a parent to create a subcategory.'),
                                        Forms\Components\TextInput::make('sort_order')
                                            ->label('Display Order')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->helperText('Lower numbers appear first in category listings.'),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->modifyQueryUsing(fn ($query) => $query->with('parent'))
            ->columns([
                Tables\Columns\TextColumn::make('name.en')
                    ->label('Name')
                    ->formatStateUsing(fn ($state, Category $record): string => AdminUi::localizedName($record->name))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Slug copied')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->getStateUsing(fn (Category $record): string => $record->parent ? AdminUi::localizedName($record->parent->name) : '—')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('blog_posts_count')
                    ->label('Posts')
                    ->counts('blogPosts')
                    ->fontMono()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sort')
                    ->numeric()
                    ->sortable()
                    ->fontMono()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Parent Category')
                    ->relationship('parent', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => AdminUi::localizedName($record->name))
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->helperText('Filter by parent category to see subcategories.'),
            ])
            ->actions(AdminUi::recordActions())
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    AdminUi::exportCsvBulkAction('Export Categories', [
                        'name' => 'Name',
                        'slug' => 'Slug',
                        'parent.name' => 'Parent',
                        'sort_order' => 'Sort Order',
                    ]),
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->emptyStateIcon('heroicon-o-folder')
            ->emptyStateHeading('No categories created yet')
            ->emptyStateDescription('Create categories to organize blog content and improve navigation.')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label('Create Category')
                    ->url(static::getUrl('create'))
                    ->icon('heroicon-o-plus')
                    ->button(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'view'   => Pages\ViewCategory::route('/{record}'),
            'edit'   => Pages\EditCategory::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug'];
    }
}
