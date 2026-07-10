<?php

namespace App\Filament\Resources;

use App\Enums\ContentStatus;
use App\Filament\Resources\PageResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\Page;
use Filament\Forms;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Notifications\NotificationAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Support\Enums\FontWeight;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-document';
    }

    protected static ?string $cluster = \App\Filament\Clusters\Content::class;

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return null;
    }

    public static function getRecordTitle(?Model $record): string|null
    {
        return $record ? AdminUi::localizedName($record->title, 'Page') : null;
    }

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
                                Section::make('Page Info')
                                    ->icon('heroicon-o-document-text')
                                    ->description('Basic page metadata and featured image.')
                                    ->schema([
                                        Forms\Components\TextInput::make('slug')
                                            ->label('URL Slug')
                                            ->placeholder('e.g. about-us, shipping-policy')
                                            ->helperText('Used in page URLs (e.g. /pages/about-us). Auto-generated from English title.')
                                            ->required()
                                            ->maxLength(200)
                                            ->unique(ignoreRecord: true),
                                        Forms\Components\Select::make('featured_image_id')
                                            ->label('Featured Image')
                                            ->relationship('featuredImage', 'file_name')
                                            ->searchable()
                                            ->nullable()
                                            ->helperText('Optional image displayed at the top of the page.'),
                                    ])->columns(2),

                                Section::make('Multilingual Page Content')
                                    ->icon('heroicon-o-language')
                                    ->description('Translate the page title, content, and SEO meta fields in supported languages.')
                                    ->schema([
                                        AdminUi::translatableTabs('Locales', [
                                            'title' => [
                                                'label' => 'Title',
                                                'required' => true,
                                                'slugSync' => true,
                                            ],
                                            'content' => [
                                                'label' => 'Content Body',
                                                'type' => 'richeditor',
                                            ],
                                        ], slugSyncTarget: 'slug', slugSyncMode: 'create-only'),
                                    ]),

                                Section::make('SEO & Meta')
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
                                                                ->label('Meta Title')
                                                                ->maxLength(255)
                                                                ->nullable()
                                                                ->helperText('Optimal: 50–60 characters. Currently shown in search results as the clickable headline.'),
                                                            Forms\Components\Textarea::make("meta_description.$code")
                                                                ->label('Meta Description')
                                                                ->rows(3)
                                                                ->nullable()
                                                                ->helperText('Optimal: 150–160 characters. Shanked beneath the title in search results.'),
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
                                Section::make('Publishing & Visibility')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->description('Control page status, scheduling, and navigation placement.')
                                    ->schema([
                                        Forms\Components\Select::make('status')
                                            ->label('Publish Status')
                                            ->options(ContentStatus::class)
                                            ->required()
                                            ->default(ContentStatus::Draft)
                                            ->helperText('Draft pages are not visible on the storefront.'),
                                        Forms\Components\DateTimePicker::make('published_at')
                                            ->label('Published At')
                                            ->nullable()
                                            ->helperText('Schedule a future publication date. Leave empty to publish immediately.'),
                                        Forms\Components\Toggle::make('is_homepage')
                                            ->label('Set as Homepage')
                                            ->default(false)
                                            ->helperText('Only one page can be set as the homepage. This will override the current homepage.'),
                                        Forms\Components\Toggle::make('is_header')
                                            ->label('Show in Header Navigation')
                                            ->default(false)
                                            ->helperText('Add this page link to the main header navigation menu.'),
                                        Forms\Components\Toggle::make('is_footer')
                                            ->label('Show in Footer Navigation')
                                            ->default(false)
                                            ->helperText('Add this page link to the footer navigation menu.'),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->columns([
            Tables\Columns\TextColumn::make('title')
                ->label('Title')
                ->getStateUsing(fn (Page $record): string => AdminUi::localizedName($record->title))
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->where(function ($q) use ($search) {
                        foreach (array_keys(AdminUi::LOCALES) as $code) {
                            $q->orWhere("title->{$code}", 'like', "%{$search}%");
                        }
                    });
                })
                ->sortable()
                ->weight(FontWeight::Medium)
                ->limit(30),
            Tables\Columns\TextColumn::make('slug')
                ->label('Slug')
                ->badge()
                ->color('gray')
                ->searchable(),
            Tables\Columns\TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->color(fn (ContentStatus $state): string => match ($state) {
                    ContentStatus::Published => 'success',
                    ContentStatus::Draft => 'warning',
                    ContentStatus::Archived => 'danger',
                    default => 'gray',
                }),
                Tables\Columns\IconColumn::make('is_homepage')
                    ->label('Home')
                    ->boolean()
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('is_header')
                    ->label('Header')
                    ->boolean()
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('is_footer')
                    ->label('Footer')
                    ->boolean()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Publish Status')
                    ->options(ContentStatus::class)
                    ->native(false)
                    ->helperText('Filter by draft, published, or archived pages.'),
                Tables\Filters\TernaryFilter::make('is_homepage')
                    ->label('Homepage')
                    ->placeholder('All')
                    ->trueLabel('Homepage Only')
                    ->falseLabel('Non-Homepage'),
                Tables\Filters\TernaryFilter::make('is_header')
                    ->label('Header Nav')
                    ->placeholder('All')
                    ->trueLabel('In Header')
                    ->falseLabel('Not in Header'),
                Tables\Filters\TernaryFilter::make('is_footer')
                    ->label('Footer Nav')
                    ->placeholder('All')
                    ->trueLabel('In Footer')
                    ->falseLabel('Not in Footer'),
            ])
            ->actions(AdminUi::recordActions())
            ->bulkActions([
            Actions\BulkActionGroup::make([
                AdminUi::exportCsvBulkAction('Export Pages', [
                    'title' => 'Title',
                    'slug' => 'Slug',
                    'status' => 'Status',
                    'is_homepage' => 'Homepage',
                    'published_at' => 'Published',
                ]),
                Actions\DeleteBulkAction::make(),
            ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateHeading('No CMS pages created yet')
            ->emptyStateDescription('Create custom landing pages, policy pages, or informational content pages.');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'view'   => Pages\ViewPage::route('/{record}'),
            'edit'   => Pages\EditPage::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title'];
    }
}

