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

                                UiSection::make('Content (JSON Config)')
                                    ->icon('heroicon-o-code-bracket')
                                    ->description('The configuration block the storefront component reads. Nested structures are supported.')
                                    ->schema([
                                        // A KeyValue field JS-fatals on the nested JSON real
                                        // sections carry — a validated JSON editor handles any shape.
                                        Forms\Components\Textarea::make('content')
                                            ->label('Content JSON')
                                            ->rows(16)
                                            ->formatStateUsing(fn ($state): string => $state
                                                ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                                                : '{}')
                                            ->dehydrateStateUsing(fn (?string $state): array => json_decode($state ?? '{}', true) ?? [])
                                            ->rules([
                                                fn (): \Closure => function (string $attribute, $value, \Closure $fail): void {
                                                    json_decode((string) $value, true);
                                                    if (json_last_error() !== JSON_ERROR_NONE) {
                                                        $fail('The content must be valid JSON: ' . json_last_error_msg());
                                                    }
                                                },
                                            ])
                                            ->extraInputAttributes(['class' => 'font-mono', 'spellcheck' => 'false'])
                                            ->helperText('Edit carefully — the structure must match what the selected section component expects. Invalid JSON is rejected on save.')
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

