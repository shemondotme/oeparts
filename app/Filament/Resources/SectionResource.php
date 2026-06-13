<?php

namespace App\Filament\Resources;

use App\Enums\SectionLocation;
use App\Enums\SectionStatus;
use App\Filament\Resources\SectionResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\Section;
use Filament\Forms;
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

    public static function getNavigationGroup(): ?string
    {
        return 'Content';
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
                                            ->options([
                                                'hero_banner' => 'Hero Banner',
                                                'trust_signals' => 'Trust Signals',
                                                'how_it_works' => 'How It Works',
                                                'stats_counter' => 'Stats Counter',
                                                'promo_banner' => 'Promo Banner',
                                                'newsletter' => 'Newsletter',
                                                'manufacturer_carousel' => 'Manufacturer Carousel',
                                                'popular_searches' => 'Popular Searches',
                                                'blog_preview' => 'Blog Preview',
                                                'faq_accordion' => 'FAQ Accordion',
                                                'testimonials' => 'Testimonials',
                                                'contact_strip' => 'Contact Strip',
                                                'announcement_bar' => 'Announcement Bar',
                                            ])
                                            ->native(false)
                                            ->required()
                                            ->live()
                                            ->helperText('Determines the visual layout and content fields available.'),
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
                                        Tabs::make('Locales')
                                            ->schema(
                                                collect(AdminUi::LOCALES)
                                                    ->map(fn (string $label, string $code) => Tab::make($label)
                                                        ->badge($code === 'en' ? 'Primary' : null)
                                                        ->schema([
                                                            Forms\Components\TextInput::make("title.$code")
                                                                ->label('Title')
                                                                ->required($code === 'en')
                                                                ->maxLength(255),
                                                        ]))
                                                    ->values()
                                                    ->all()
                                            )
                                            ->columnSpanFull(),
                                    ]),

                                UiSection::make('Content (JSON Config)')
                                    ->icon('heroicon-o-code-bracket')
                                    ->description('Enter raw JSON configuration block tailored for the selected section type.')
                                    ->schema([
                                        Forms\Components\KeyValue::make('content')
                                            ->label('Content Details')
                                            ->keyLabel('Field')
                                            ->valueLabel('Value')
                                            ->columnSpanFull(),
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
                    ->getStateUsing(fn (Model $record): string => ucwords(str_replace('_', ' ', $record->type)))
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
                    ->options([
                        'hero_banner' => 'Hero Banner',
                        'trust_signals' => 'Trust Signals',
                        'how_it_works' => 'How It Works',
                        'stats_counter' => 'Stats Counter',
                        'promo_banner' => 'Promo Banner',
                        'newsletter' => 'Newsletter',
                        'manufacturer_carousel' => 'Manufacturer Carousel',
                        'popular_searches' => 'Popular Searches',
                        'blog_preview' => 'Blog Preview',
                        'faq_accordion' => 'FAQ Accordion',
                        'testimonials' => 'Testimonials',
                        'contact_strip' => 'Contact Strip',
                        'announcement_bar' => 'Announcement Bar',
                    ])
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

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', \App\Enums\SectionStatus::Published)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title'];
    }
}

