<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SeoMetaResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\SeoMeta;
use Filament\Forms;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Notifications\NotificationAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;

class SeoMetaResource extends Resource
{
    protected static ?string $model = SeoMeta::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-magnifying-glass-circle';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Administration';
    }

    public static function getNavigationSort(): ?int
    {
        return 40;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'metable_type';
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
                                Section::make('Resource Details')
                                    ->icon('heroicon-o-document-text')
                                    ->description('SEO metadata for the linked resource (product, page, blog post, etc.).')
                                    ->schema([
                                        AdminUi::readOnlyField('metable_type', 'Resource Type', 'The model type this SEO metadata belongs to.'),
                                        AdminUi::readOnlyField('metable_id', 'Resource ID'),
                                        Forms\Components\TextInput::make('meta_title')
                                            ->label('Meta Title')
                                            ->placeholder('e.g. Brake Pads for VW Golf | OeParts')
                                            ->maxLength(255)
                                            ->nullable()
                                            ->helperText('Optimal: 50–60 characters. Currently shown in search results as the clickable headline.'),
                                        Forms\Components\Textarea::make('meta_description')
                                            ->label('Meta Description')
                                            ->placeholder('e.g. Find genuine OEM brake pads for your VW Golf...')
                                            ->rows(3)
                                            ->nullable()
                                            ->columnSpanFull()
                                            ->helperText('Optimal: 150–160 characters. Shanked beneath the title in search results.'),
                                    ])->columns(2),

                                Section::make('Canonical URL')
                                    ->icon('heroicon-o-link')
                                    ->description('Set the canonical URL to prevent duplicate content issues.')
                                    ->schema([
                                        Forms\Components\TextInput::make('canonical_url')
                                            ->label('Canonical URL')
                                            ->placeholder('e.g. https://oeparts.com/products/brake-pads')
                                            ->url()
                                            ->maxLength(500)
                                            ->nullable()
                                            ->columnSpanFull()
                                            ->helperText('The preferred URL for this resource. Leave empty to use the default URL.'),
                                    ]),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Social & Indexing')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->description('Open Graph tags and search engine indexing directives.')
                                    ->schema([
                                        Forms\Components\TextInput::make('og_title')
                                            ->label('Open Graph Title')
                                            ->placeholder('e.g. Brake Pads for VW Golf | OeParts')
                                            ->maxLength(255)
                                            ->nullable()
                                            ->helperText('Title shown when shared on social media. Falls back to meta title if empty.'),
                                        Forms\Components\Textarea::make('og_description')
                                            ->label('Open Graph Description')
                                            ->placeholder('e.g. Find genuine OEM brake pads...')
                                            ->rows(2)
                                            ->nullable()
                                            ->helperText('Description shown when shared on social media. Falls back to meta description if empty.'),
                                        Forms\Components\Select::make('robots')
                                            ->label('Robots Directive')
                                            ->options([
                                                'index,follow' => 'Index, Follow',
                                                'noindex,follow' => 'No Index, Follow',
                                                'index,nofollow' => 'Index, No Follow',
                                                'noindex,nofollow' => 'No Index, No Follow',
                                            ])
                                            ->native(false)
                                            ->nullable()
                                            ->helperText('Control how search engines crawl and index this page.'),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->columns([
            Tables\Columns\TextColumn::make('metable_type')
                ->label('Type')
                ->badge()
                ->color('gray')
                ->searchable()
                ->sortable()
                ->weight(FontWeight::Medium),
            Tables\Columns\TextColumn::make('metable_id')
                ->label('ID')
                ->alignCenter()
                ->fontMono(),
            Tables\Columns\TextColumn::make('meta_title')
                ->label('Meta Title')
                ->limit(40)
                ->searchable(),
            Tables\Columns\TextColumn::make('robots')
                ->label('Robots')
                ->badge()
                ->color('primary')
                ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('metable_type')
                    ->label('Resource Type')
                    ->options(fn (): array => SeoMeta::distinct()->pluck('metable_type', 'metable_type')->toArray())
                    ->native(false)
                    ->helperText('Filter SEO metadata by the linked model type.'),
                Tables\Filters\SelectFilter::make('robots')
                    ->label('Robots Directive')
                    ->options([
                        'index,follow' => 'Index, Follow',
                        'noindex,follow' => 'No Index, Follow',
                    ])
                    ->native(false)
                    ->helperText('Filter by search engine indexing directive.'),
            ])
            ->actions(AdminUi::recordActionsWithoutView())
            ->bulkActions([
            Actions\BulkActionGroup::make([
                AdminUi::exportCsvBulkAction('Export SEO Meta', [
                    'metable_type' => 'Type',
                    'meta_title' => 'Title',
                    'meta_description' => 'Description',
                    'robots' => 'Robots',
                ]),
                Actions\DeleteBulkAction::make(),
            ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-magnifying-glass-circle')
            ->emptyStateHeading('No SEO metadata configured yet')
            // No emptyStateActions — see ListSeoMetas::getHeaderActions()
            // for why there's no working "create" entry point (the
            // polymorphic metable_type/metable_id target can't be set on
            // a blank form). This empty-state button used to link to
            // getUrl('create'), which threw RouteNotFoundException once
            // that route was removed, confirmed live.
            ->emptyStateDescription('SEO metadata records for products, pages, and blog posts will appear here once generated.');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        // No 'create' route — see ListSeoMetas::getHeaderActions() for why:
        // the polymorphic metable_type/metable_id target can never be set
        // via a blank create form, so the route was reachable-but-always-
        // guaranteed-to-crash even with its header button removed.
        return [
            'index' => Pages\ListSeoMetas::route('/'),
            'edit'  => Pages\EditSeoMeta::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['meta_title', 'meta_description'];
    }
}

