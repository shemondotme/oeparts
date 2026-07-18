<?php

namespace App\Filament\Resources;

use App\Enums\SectionLocation;
use App\Enums\SectionStatus;
use App\Filament\Resources\SectionResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\Section;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Notifications\Notification;
use Filament\Notifications\NotificationAction;
use Filament\Actions;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Filament\Schemas\Components\Section as UiSection;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Enums\FontWeight;

class SectionResource extends Resource
{
    protected static ?string $model = Section::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-squares-2x2';
    }

    protected static ?string $cluster = \App\Filament\Clusters\Content::class;

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return null;
    }

    public static function getRecordTitle(?Model $record): string|null
    {
        if (!$record instanceof Section) {
            return null;
        }
        return AdminUi::localizedName($record->title, 'Section');
    }

    /**
     * A single (non-translatable) content field that only shows — and only
     * saves — for the given section type(s). ->visible() alone does not stop
     * Filament from dehydrating a hidden field, so ->dehydrated() must mirror
     * the same condition, or switching `type` in the form (without saving in
     * between) would silently null out a different type's content on save.
     */
    private static function typedField(string $fieldName, string $label, \Closure $visibleWhen, array $opts = [])
    {
        $field = match ($opts['type'] ?? 'text') {
            'textarea' => Forms\Components\Textarea::make($fieldName)->rows($opts['rows'] ?? 3),
            'tags' => Forms\Components\TagsInput::make($fieldName)->placeholder($opts['placeholder'] ?? 'Add…'),
            default => Forms\Components\TextInput::make($fieldName)->maxLength($opts['maxLength'] ?? 255),
        };

        return $field
            ->label($label)
            ->helperText($opts['helperText'] ?? null)
            ->visible($visibleWhen)
            ->dehydrated($visibleWhen)
            ->columnSpanFull();
    }

    /**
     * A translatable (per-locale) content field group that only shows/saves
     * for the given section type(s) — same dehydration caveat as typedField()
     * above, applied to each locale's underlying field individually since
     * Tabs (a layout component) doesn't dehydrate on behalf of its children.
     */
    private static function typedTranslatableField(string $fieldName, string $label, \Closure $visibleWhen, array $opts = []): Tabs
    {
        $type = $opts['type'] ?? 'text';

        return Tabs::make($label)
            ->schema(
                collect(AdminUi::LOCALES)
                    ->map(function (string $localeLabel, string $code) use ($fieldName, $label, $type, $opts, $visibleWhen) {
                        $name = "{$fieldName}.{$code}";
                        $field = $type === 'textarea'
                            ? Forms\Components\Textarea::make($name)->rows($opts['rows'] ?? 3)
                            : Forms\Components\TextInput::make($name)->maxLength($opts['maxLength'] ?? 255);

                        return Tab::make($localeLabel)
                            ->badge($code === 'en' ? 'Primary' : null)
                            ->schema([
                                $field
                                    ->label($label)
                                    ->helperText($code === 'en'
                                        ? ($opts['helperText'] ?? null)
                                        : 'Leave blank to fall back to the English value.')
                                    ->visible($visibleWhen)
                                    ->dehydrated($visibleWhen),
                            ]);
                    })
                    ->values()
                    ->all()
            )
            ->visible($visibleWhen)
            ->columnSpanFull();
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
                                UiSection::make('Section Configuration')
                                    ->icon('heroicon-o-squares-2x2')
                                    ->description('Define the section type and where it appears on the page.')
                                    ->schema([
                                        Forms\Components\Select::make('type')
                                            ->label('Section Type')
                                            // Section::TYPES mirrors the real storefront components —
                                            // the previous hand-typed list matched NONE of them.
                                            ->options(Section::TYPES)
                                            ->native(false)
                                            ->required()
                                            ->live()
                                            ->helperText('Must match a storefront section component — the homepage silently skips unknown types.'),
                                        Forms\Components\Select::make('location')
                                            ->label('Page Location')
                                            ->options(SectionLocation::class)
                                            ->native(false)
                                            ->required()
                                            ->helperText('Which page or area of the site this section appears on.'),
                                    ])->columns(2),

                                UiSection::make('Multilingual Title')
                                    ->icon('heroicon-o-language')
                                    ->schema([
                                        AdminUi::translatableTabs('Locales', [
                                            'title' => [
                                                'label' => 'Title',
                                                'required' => true,
                                            ],
                                        ]),
                                    ]),

                                UiSection::make('Content')
                                    ->icon('heroicon-o-rectangle-stack')
                                    ->description('Fields shown here depend on the selected Section Type above — only the fields that section actually reads are displayed.')
                                    ->schema([
                                        self::typedTranslatableField('content.eyebrow', 'Eyebrow', fn (Get $get) => $get('type') !== 'trust_bar'),
                                        self::typedTranslatableField('content.headline', 'Headline', fn (Get $get) => $get('type') !== 'trust_bar'),
                                        self::typedTranslatableField('content.subheadline', 'Subheadline', fn (Get $get) => $get('type') !== 'trust_bar', ['type' => 'textarea', 'rows' => 2]),

                                        self::typedTranslatableField('content.button_text', 'Button Text', fn (Get $get) => in_array($get('type'), ['banner', 'contact_cta', 'newsletter', 'part_inquiry', 'hero'])),
                                        self::typedTranslatableField('content.view_all_text', 'View All Text', fn (Get $get) => in_array($get('type'), ['featured_brands', 'blog_preview'])),
                                        self::typedTranslatableField('content.search_cta_text', 'Search CTA Text', fn (Get $get) => $get('type') === 'popular_searches'),
                                        self::typedTranslatableField('content.placeholder', 'Input Placeholder', fn (Get $get) => in_array($get('type'), ['hero', 'newsletter'])),
                                        self::typedTranslatableField('content.success_text', 'Success Message', fn (Get $get) => $get('type') === 'newsletter'),

                                        self::typedField('content.button_url', 'Button URL', fn (Get $get) => $get('type') === 'banner'),
                                        self::typedField('content.popular_oem', 'Popular OEM Numbers', fn (Get $get) => $get('type') === 'hero', [
                                            'type' => 'tags',
                                            'helperText' => 'Shown as quick-search suggestions on the homepage hero when no live popular-OEM data is available.',
                                        ]),

                                        Repeater::make('content.items')
                                            ->label('Trust Bar Items')
                                            ->schema([
                                                Forms\Components\TextInput::make('icon')
                                                    ->label('Icon')
                                                    ->maxLength(60)
                                                    ->helperText('Heroicon name, e.g. truck, shield-check, arrow-path, lock-closed.'),
                                                AdminUi::translatableTabs('Text', [
                                                    'text' => ['label' => 'Text'],
                                                ]),
                                            ])
                                            ->reorderable()
                                            ->collapsible()
                                            ->itemLabel(fn (array $state): ?string => $state['text']['en'] ?? null)
                                            ->visible(fn (Get $get) => $get('type') === 'trust_bar')
                                            ->dehydrated(fn (Get $get) => $get('type') === 'trust_bar')
                                            ->columnSpanFull(),

                                        Repeater::make('content.items')
                                            ->label('Stat Items')
                                            ->schema([
                                                Forms\Components\Select::make('key')
                                                    ->label('Metric Key')
                                                    ->options([
                                                        'parts_count' => 'Parts Count',
                                                        'customers_count' => 'Customers Count',
                                                        'countries_count' => 'Countries Count',
                                                        'rating' => 'Rating',
                                                        'orders_count' => 'Orders Count',
                                                        'brands_count' => 'Brands Count',
                                                        'categories_count' => 'Categories Count',
                                                    ])
                                                    ->native(false)
                                                    ->required()
                                                    ->helperText('Must match a real settings key under Stats Counter Settings.'),
                                                Forms\Components\TextInput::make('suffix')->label('Suffix')->maxLength(10)->placeholder('+'),
                                                AdminUi::translatableTabs('Label', [
                                                    'label' => ['label' => 'Label'],
                                                ]),
                                            ])
                                            ->reorderable()
                                            ->collapsible()
                                            ->itemLabel(fn (array $state): ?string => $state['label']['en'] ?? ($state['key'] ?? null))
                                            ->visible(fn (Get $get) => $get('type') === 'stats_counter')
                                            ->dehydrated(fn (Get $get) => $get('type') === 'stats_counter')
                                            ->columnSpanFull(),

                                        Repeater::make('content.features')
                                            ->label('Shipping Features')
                                            ->schema([
                                                Forms\Components\TextInput::make('icon')
                                                    ->label('Icon')
                                                    ->maxLength(60)
                                                    ->helperText('Heroicon name, e.g. truck, globe-europe-africa, clock, gift, arrow-path, map-pin, shield-check.'),
                                                AdminUi::translatableTabs('Value', [
                                                    'value' => ['label' => 'Value'],
                                                ]),
                                                AdminUi::translatableTabs('Label', [
                                                    'label' => ['label' => 'Label'],
                                                ]),
                                            ])
                                            ->reorderable()
                                            ->collapsible()
                                            ->itemLabel(fn (array $state): ?string => $state['label']['en'] ?? null)
                                            ->visible(fn (Get $get) => $get('type') === 'shipping_info')
                                            ->dehydrated(fn (Get $get) => $get('type') === 'shipping_info')
                                            ->columnSpanFull(),

                                        Forms\Components\TagsInput::make('content.carriers')
                                            ->label('Carriers')
                                            ->placeholder('Add a carrier…')
                                            ->helperText('e.g. DHL, DPD, GLS, FedEx, UPS')
                                            ->visible(fn (Get $get) => $get('type') === 'shipping_info')
                                            ->dehydrated(fn (Get $get) => $get('type') === 'shipping_info')
                                            ->columnSpanFull(),

                                        Repeater::make('content.steps')
                                            ->label('Steps')
                                            ->schema([
                                                Forms\Components\TextInput::make('icon')
                                                    ->label('Icon')
                                                    ->maxLength(60)
                                                    ->helperText('Heroicon name, e.g. magnifying-glass, shopping-cart, truck.'),
                                                Forms\Components\TextInput::make('step_number')
                                                    ->label('Step Number')
                                                    ->numeric(),
                                                AdminUi::translatableTabs('Title', [
                                                    'title' => ['label' => 'Title'],
                                                ]),
                                                AdminUi::translatableTabs('Description', [
                                                    'description' => ['label' => 'Description', 'type' => 'textarea', 'rows' => 2],
                                                ]),
                                            ])
                                            ->reorderable()
                                            ->collapsible()
                                            ->itemLabel(fn (array $state): ?string => $state['title']['en'] ?? null)
                                            ->visible(fn (Get $get) => $get('type') === 'how_it_works')
                                            ->dehydrated(fn (Get $get) => $get('type') === 'how_it_works')
                                            ->columnSpanFull(),

                                        self::typedField('content.phone', 'Legacy Phone (unused)', fn (Get $get) => $get('type') === 'contact_cta', [
                                            'helperText' => 'Not read by the storefront — Contact CTA now uses General Settings → Public Contact Phone. Kept here only so no existing data is lost.',
                                        ]),
                                        self::typedField('content.bg_style', 'Legacy Background Style (unused)', fn (Get $get) => $get('type') === 'hero', [
                                            'helperText' => 'Not currently read by the storefront hero component. Kept here only so no existing data is lost.',
                                        ]),
                                    ]),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                UiSection::make('Publish Settings')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->description('Control when and how this section is displayed.')
                                    ->schema([
                                        Forms\Components\Select::make('status')
                                            ->label('Publish Status')
                                            ->options(SectionStatus::class)
                                            ->native(false)
                                            ->required()
                                            ->default(SectionStatus::Draft)
                                            ->helperText('Draft sections are not rendered on the storefront.'),
                                        Forms\Components\DateTimePicker::make('publish_at')
                                            ->label('Scheduled Publish')
                                            ->nullable()
                                            ->helperText('Schedule a future publication date. Leave empty to publish immediately.'),
                                        Forms\Components\TextInput::make('sort_order')
                                            ->label('Display Order')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->helperText('Lower numbers appear first within the same page location.'),
                                        Forms\Components\Toggle::make('is_active')
                                            ->label('Section Active')
                                            ->helperText('Inactive sections are hidden from the storefront.')
                                            ->default(true),
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
                ->getStateUsing(fn (Section $record): string => AdminUi::localizedName($record->title, 'Section'))
                ->searchable()
                ->weight(FontWeight::Medium)
                ->limit(30),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color('gray')
                    ->getStateUsing(fn (Model $record): string => Section::TYPES[$record->type] ?? ucwords(str_replace('_', ' ', $record->type)))
                    ->searchable(),
                Tables\Columns\TextColumn::make('location')
                    ->label('Location')
                    ->badge()
                    ->color(fn (SectionLocation $state): string => $state === SectionLocation::Homepage ? 'primary' : 'info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (SectionStatus $state): string => match ($state) {
                        SectionStatus::Published => 'success',
                        SectionStatus::Draft     => 'gray',
                        SectionStatus::Scheduled => 'warning',
                        SectionStatus::Archived  => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sort')
                    ->fontMono()
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('publish_at')
                    ->label('Publish At')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Section Type')
                    ->options(Section::TYPES)
                    ->native(false)
                    ->helperText('Filter by the type of content section.'),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Publish Status')
                    ->options(SectionStatus::class)
                    ->native(false)
                    ->helperText('Filter by draft, published, scheduled, or archived.'),
                Tables\Filters\SelectFilter::make('location')
                    ->label('Page Location')
                    ->options(SectionLocation::class)
                    ->native(false)
                    ->helperText('Filter by where the section appears.'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Section Status')
                    ->placeholder('All')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),
            ])
            ->reorderable('sort_order')
            ->actions([
                ...AdminUi::recordActions([
                    Actions\Action::make('publish')
                        ->label('Publish')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->authorize('update')
                        ->requiresConfirmation()
                        ->visible(fn (Section $record): bool => $record->status !== SectionStatus::Published)
                        ->action(function (Section $record): void {
                            $record->publish();

                            Notification::make()
                                ->title('Section published')
                                ->success()
                                ->send();
                        }),
                    Actions\Action::make('archive')
                        ->label('Archive')
                        ->icon('heroicon-o-archive-box')
                        ->color('gray')
                        ->authorize('update')
                        ->requiresConfirmation()
                        ->visible(fn (Section $record): bool => $record->status !== SectionStatus::Archived)
                        ->action(function (Section $record): void {
                            $record->archive();

                            Notification::make()
                                ->title('Section archived')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->bulkActions([
            Actions\BulkActionGroup::make([
                AdminUi::exportCsvBulkAction('Export Sections', [
                    'title' => 'Title',
                    'type' => 'Type',
                    'location' => 'Location',
                    'status' => 'Status',
                    'is_active' => 'Active',
                ]),
                Actions\DeleteBulkAction::make(),
            ]),
            ])
            ->defaultSort('sort_order', 'asc')
            ->emptyStateIcon('heroicon-o-squares-2x2')
            ->emptyStateHeading('No sections configured yet')
            ->emptyStateDescription('Add page sections to build your homepage layout and content structure.');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSections::route('/'),
            'create' => Pages\CreateSection::route('/create'),
            'view'   => Pages\ViewSection::route('/{record}'),
            'edit'   => Pages\EditSection::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title'];
    }
}

