<?php

namespace App\Filament\Resources;

use App\Enums\ContentStatus;
use App\Filament\Resources\BlogPostResource\Pages;
use App\Filament\Resources\BlogPostResource\RelationManagers;
use App\Filament\Support\AdminUi;
use App\Models\BlogPost;
use Filament\Forms;
use Filament\Actions;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Support\Enums\FontWeight;

class BlogPostResource extends Resource
{
    protected static ?string $model = BlogPost::class;

    protected static ?string $recordTitleAttribute = null;

    public static function getRecordTitle(?Model $record): string|null
    {
        return $record ? AdminUi::localizedName($record->title, 'Blog Post') : null;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-newspaper';
    }

    protected static ?string $cluster = \App\Filament\Clusters\Content::class;

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
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
                                Section::make('Content Details')
                                    ->icon('heroicon-o-document-text')
                                    ->description('Post metadata including URL, categorization, and featured image.')
                                    ->schema([
                                        Forms\Components\TextInput::make('slug')
                                            ->label(__('admin.url_slug'))
                                            ->placeholder('e.g. how-to-choose-brake-pads')
                                            ->helperText('Used in blog post URLs (e.g. /blog/how-to-choose-brake-pads). Auto-generated from English title.')
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(200),
                                        Forms\Components\Select::make('category_id')
                                            ->label(__('admin.blog_category'))
                                            ->relationship('category', 'name')
                                            ->getOptionLabelFromRecordUsing(fn ($record) => AdminUi::localizedName($record->name))
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->helperText('Organize posts into categories for better navigation.'),
                                        Forms\Components\Select::make('tags')
                                            ->label(__('admin.tags'))
                                            ->relationship('tags', 'name')
                                            ->getOptionLabelFromRecordUsing(fn ($record) => AdminUi::localizedName($record->name))
                                            ->multiple()
                                            ->preload()
                                            ->createOptionForm([
                                                Forms\Components\TextInput::make('name')
                                                    ->label(__('admin.tag_name'))
                                                    ->required()
                                                    ->maxLength(100)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn ($state, callable $set) => filled($state) ? $set('slug', \Illuminate\Support\Str::slug($state)) : null),
                                                Forms\Components\TextInput::make('slug')
                                                    ->required()
                                                    ->maxLength(200)
                                                    ->unique(table: 'blog_tags')
                                                    ->helperText('Auto-filled from the name.'),
                                            ])
                                            ->createOptionUsing(fn (array $data): int => \App\Models\BlogTag::create([
                                                'name' => ['en' => $data['name']],
                                                'slug' => $data['slug'],
                                            ])->id)
                                            ->helperText('Add tags for content discovery and SEO — create new ones inline with the + button.'),
                                        Forms\Components\Select::make('featured_image_id')
                                            ->label(__('admin.featured_image'))
                                            ->relationship('featuredImage', 'file_name')
                                            ->searchable()
                                            ->nullable()
                                            ->helperText('Thumbnail image shown in blog listings and social sharing.'),
                                    ])->columns(2),

                                Section::make('Multilingual Content')
                                    ->icon('heroicon-o-language')
                                    ->description('Translate the post title, excerpt, and content body for each supported language.')
                                    ->schema([
                                        AdminUi::translatableTabs('Locales', [
                                            'title' => [
                                                'label' => 'Post Title',
                                                'placeholder' => 'e.g. How to Choose the Right Brake Pads',
                                                'required' => true,
                                                'helperText' => 'English title is required and used as the default fallback.',
                                                'slugSync' => true,
                                            ],
                                            'excerpt' => [
                                                'label' => 'Excerpt',
                                                'type' => 'textarea',
                                                'rows' => 3,
                                                'placeholder' => 'Brief summary for blog listings and social sharing...',
                                                'helperText' => 'Short summary shown in blog listings. Leave empty to auto-generate from content.',
                                            ],
                                            'content' => [
                                                'label' => 'Content Body',
                                                'type' => 'richeditor',
                                            ],
                                        ], slugSyncTarget: 'slug', slugSyncMode: 'create-only'),
                                    ]),

                                Section::make('SEO & Metadata')
                                    ->icon('heroicon-o-globe-alt')
                                    ->description('Search engine optimization settings to improve visibility in search results.')
                                    ->collapsible()
                                    ->schema([
                                        Tabs::make('SeoLocales')
                                            ->schema(
                                                collect(AdminUi::LOCALES)
                                                    ->map(fn (string $label, string $code) => Tab::make($label)
                                                        ->schema([
                                                            Forms\Components\TextInput::make("meta_title.$code")
                                                                ->label(__('admin.meta_title'))
                                                                ->placeholder('e.g. How to Choose Brake Pads | OeParts')
                                                                ->maxLength(255)
                                                                ->nullable()
                                                                ->helperText('Optimal: 50–60 characters. Currently shown in search results as the clickable headline.'),
                                                            Forms\Components\Textarea::make("meta_description.$code")
                                                                ->label(__('admin.meta_description'))
                                                                ->placeholder('e.g. Learn how to select the right brake pads for your vehicle...')
                                                                ->rows(2)
                                                                ->maxLength(500)
                                                                ->nullable()
                                                                ->helperText('Optimal: 150–160 characters. Shown beneath the title in search results.'),
                                                        ]))
                                                    ->values()
                                                    ->all()
                                            )
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Publishing Settings')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->description('Control when and how this post is published.')
                                    ->schema([
                                        Forms\Components\Select::make('status')
                                            ->label(__('admin.publish_status'))
                                            ->options(ContentStatus::class)
                                            ->required()
                                            ->default(ContentStatus::Draft)
                                            ->helperText('Draft posts are not visible on the storefront.'),
                                        Forms\Components\DateTimePicker::make('published_at')
                                            ->label(__('admin.published_at'))
                                            ->nullable()
                                            ->helperText('Schedule a future publication date. Leave empty to publish immediately.'),
                                        Forms\Components\DatePicker::make('last_reviewed_at')
                                            ->label(__('admin.last_reviewed'))
                                            ->nullable()
                                            ->helperText('Track when this post was last fact-checked or updated.'),
                                        Forms\Components\Select::make('author_id')
                                            ->label(__('admin.author'))
                                            ->relationship('author', 'name')
                                            ->required()
                                            ->default(fn () => auth('admin')->id())
                                            ->helperText('The admin user credited as the author of this post.'),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->modifyQueryUsing(fn ($query) => $query->with(['category', 'author']))
            ->columns([
            Tables\Columns\TextColumn::make('title')
                ->label(__('admin.title'))
                ->getStateUsing(fn (BlogPost $record): string => AdminUi::localizedName($record->title))
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->where(function ($q) use ($search) {
                        foreach (array_keys(AdminUi::LOCALES) as $code) {
                            $q->orWhere("title->{$code}", 'like', "%{$search}%");
                        }
                    });
                })
                ->sortable()
                ->weight(FontWeight::Medium)
                ->limit(40),
            Tables\Columns\TextColumn::make('category.name')
                ->label(__('admin.category'))
                ->getStateUsing(fn (BlogPost $record): string => $record->category ? AdminUi::localizedName($record->category->name) : '—')
                ->badge()
                ->color('gray')
                ->limit(20),
            Tables\Columns\TextColumn::make('author.name')
                ->label(__('admin.author'))
                ->toggleable(),
            Tables\Columns\TextColumn::make('status')
                ->label(__('admin.status'))
                ->badge()
                ->color(fn (ContentStatus $state): string => match ($state) {
                    ContentStatus::Published => 'success',
                    ContentStatus::Draft => 'warning',
                    ContentStatus::Archived => 'danger',
                    default => 'gray',
                }),
                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('admin.publish_status'))
                    ->options(ContentStatus::class)
                    ->native(false)
                    ->helperText('Filter by draft, published, or archived posts.'),
                Tables\Filters\SelectFilter::make('category_id')
                    ->label(__('admin.blog_category'))
                    ->relationship('category', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => AdminUi::localizedName($record->name))
                    ->native(false)
                    ->helperText('Filter posts by their assigned category.'),
                Tables\Filters\Filter::make('created_at')
                    ->label(__('admin.created_date'))
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label(__('admin.created_after'))
                            ->placeholder('Select start date'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label(__('admin.created_before'))
                            ->placeholder('Select end date'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['created_from'], fn ($q) => $q->whereDate('created_at', '>=', $data['created_from']))
                            ->when($data['created_until'], fn ($q) => $q->whereDate('created_at', '<=', $data['created_until']));
                    }),
            ])
            ->actions(AdminUi::recordActions(after: [
                Actions\Action::make('togglePublish')
                    ->label(fn (BlogPost $record): string => $record->status === ContentStatus::Published ? 'Unpublish' : 'Publish')
                    ->icon(fn (BlogPost $record): string => $record->status === ContentStatus::Published ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (BlogPost $record): string => $record->status === ContentStatus::Published ? 'warning' : 'success')
                    ->authorize('update')
                    ->requiresConfirmation()
                    ->modalHeading(fn (BlogPost $record): string => $record->status === ContentStatus::Published ? 'Unpublish Post' : 'Publish Post')
                    ->modalDescription(fn (BlogPost $record): string => $record->status === ContentStatus::Published
                        ? 'Unpublish "' . ($record->title['en'] ?? '') . '"? It will revert to draft.'
                        : 'Publish "' . ($record->title['en'] ?? '') . '"? It will become visible on the storefront.')
                    ->action(function (BlogPost $record) {
                        $newStatus = $record->status === ContentStatus::Published
                            ? ContentStatus::Draft
                            : ContentStatus::Published;

                        $record->update([
                            'status' => $newStatus,
                            'published_at' => $newStatus === ContentStatus::Published ? now() : null,
                        ]);

                        Notification::make()
                            ->title($newStatus === ContentStatus::Published ? 'Post published' : 'Post unpublished')
                            ->success()
                            ->send();
                    }),
            ]))
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    AdminUi::impactBulkAction(
                        name: 'bulkPublish',
                        label: 'Publish',
                        color: 'success',
                        icon: 'heroicon-o-eye',
                        summary: fn ($record): ?array => $record->status === ContentStatus::Published
                            ? null
                            : [
                                'key' => trans_field($record->title),
                                'old' => $record->status->value,
                                'new' => ContentStatus::Published->value,
                            ],
                        action: function ($records) {
                            $records->each(function (BlogPost $record) {
                                $record->update([
                                    'status' => ContentStatus::Published,
                                    'published_at' => $record->published_at ?? now(),
                                ]);
                            });

                            Notification::make()
                                ->title($records->count() . ' posts published')
                                ->success()
                                ->send();
                        },
                    ),
                    AdminUi::impactBulkAction(
                        name: 'bulkArchive',
                        label: 'Unpublish',
                        color: 'danger',
                        icon: 'heroicon-o-archive-box',
                        summary: fn ($record): ?array => $record->status !== ContentStatus::Published
                            ? null
                            : [
                                'key' => trans_field($record->title),
                                'old' => $record->status->value,
                                'new' => ContentStatus::Draft->value,
                            ],
                        action: function ($records) {
                            $records->each(function (BlogPost $record) {
                                $record->update(['status' => ContentStatus::Draft]);
                            });

                            Notification::make()
                                ->title($records->count() . ' posts unpublished')
                                ->success()
                                ->send();
                        },
                    ),
                    AdminUi::exportCsvBulkAction('Export Blog Posts', [
                        'title' => 'Title',
                        'slug' => 'Slug',
                        'status' => 'Status',
                        'category.name' => 'Category',
                        'author.name' => 'Author',
                        'created_at' => 'Date',
                    ]),
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('published_at', 'desc')
            ->emptyStateIcon('heroicon-o-newspaper')
            ->emptyStateHeading('No blog posts created yet')
            ->emptyStateDescription('Write and publish your first blog post to share technical guides, news, and updates with your customers.')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label(__('admin.create_post'))
                    ->url(static::getUrl('create'))
                    ->icon('heroicon-o-plus')
                    ->button(),
            ]);
    }

    public static function getRelations(): array
    {
        // Tags are managed by the form's multiselect (with inline creation) —
        // the old relation manager duplicated it and asked operators to type
        // raw {"en": ...} JSON into a text field.
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

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'slug', 'excerpt'];
    }
}

