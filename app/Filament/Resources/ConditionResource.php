<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConditionResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\Condition;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;

class ConditionResource extends Resource
{
    protected static ?string $model = Condition::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-tag';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Catalog';
    }

    public static function getNavigationSort(): ?int
    {
        return 45;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'name';
    }

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
                                Section::make('Condition Details')
                                    ->icon('heroicon-o-tag')
                                    ->description('Basic information and display settings for this part condition.')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label(__('admin.condition_name'))
                                            ->placeholder('e.g. New, Used, Remanufactured')
                                            ->required()
                                            ->maxLength(100)
                                            ->live(onBlur: true)
                                            // Filament 5 moved Get/Set to Schemas\Components\Utilities — the
                                            // old Filament\Forms\Get/Set type-hints are a distinct, incompatible
                                            // class that Livewire's dependency injection never actually passes
                                            // here, so every keystroke on this field threw a TypeError (the
                                            // real runtime argument is Schemas\Components\Utilities\Get),
                                            // confirmed live — auto-slug-from-name was completely broken on
                                            // Condition create, the only resource in the codebase using this
                                            // stale v3-era type-hint (rule #38's "grep the whole codebase"
                                            // lesson applies here too — every other resource already uses the
                                            // v5 Utilities namespace or doesn't use Get/Set at all).
                                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state, ?Condition $record) {
                                                if (! $record && blank($get('slug'))) {
                                                    $set('slug', str($state)->slug());
                                                }
                                            }),
                                        Forms\Components\TextInput::make('slug')
                                            ->label(__('admin.url_slug'))
                                            ->placeholder('e.g. new, used, remanufactured')
                                            ->required()
                                            ->maxLength(100)
                                            ->unique(ignoreRecord: true)
                                            ->rules(['regex:/^[a-z0-9\-]+$/'])
                                            ->helperText('Unique URL-safe identifier. Lowercase letters, numbers, and hyphens only.'),
                                        Forms\Components\TextInput::make('sort_order')
                                            ->label(__('admin.sort_order'))
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->helperText('Lower numbers appear first in condition lists and filters.'),
                                        Forms\Components\Toggle::make('is_active')
                                            ->label(__('admin.active'))
                                            ->helperText('Inactive conditions are hidden from the storefront and product forms.')
                                            ->default(true),
                                    ])->columns(2),
                            ]),

                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Badge Colors')
                                    ->icon('heroicon-o-swatch')
                                    ->description('Colors used for the condition badge throughout the storefront and admin.')
                                    ->schema([
                                        Forms\Components\ColorPicker::make('bg_color')
                                            ->label(__('admin.background_color'))
                                            ->required()
                                            ->default('#DCFCE7')
                                            ->live()
                                            ->helperText('Badge background color (hex).'),
                                        Forms\Components\ColorPicker::make('text_color')
                                            ->label(__('admin.text_color'))
                                            ->required()
                                            ->default('#16A34A')
                                            ->live()
                                            ->helperText('Badge text color (hex).'),
                                        Forms\Components\Placeholder::make('preview')
                                            ->label(__('admin.preview'))
                                            ->content(fn (\Filament\Schemas\Components\Utilities\Get $get) => sprintf(
                                                '<span class="inline-flex items-center rounded px-2 py-0.5 bp-spec-mono font-bold" style="background-color: %s; color: %s;">%s</span>',
                                                $get('bg_color') ?? '#DCFCE7',
                                                $get('text_color') ?? '#16A34A',
                                                $get('name') ?? 'Preview'
                                            ))
                                            ->html()
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('admin.sort'))
                    ->fontMono()
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                // One column, keyed 'name': two columns previously shared this
                // key, so the second ("Badge") silently REPLACED the first and
                // the list lost its Name/Slug columns entirely. The badge IS
                // the name presentation — rendered in the condition's colors.
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.name'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->weight(FontWeight::Medium)
                    ->color(fn (Condition $record) => $record->bg_color
                        ? \Filament\Support\Colors\Color::hex($record->bg_color)
                        : 'gray'),
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('admin.slug'))
                    ->fontMono()
                    ->copyable()
                    ->copyMessage('Slug copied'),
                Tables\Columns\TextColumn::make('products_count')
                    ->label(__('admin.products'))
                    ->counts('products')
                    ->fontMono()
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('is_active')
                    ->label(__('admin.active'))
                    ->badge()
                    ->alignCenter()
                    ->getStateUsing(fn (Condition $record): string => $record->is_active ? 'Active' : 'Inactive')
                    ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'gray')
                    ->icon(fn (string $state): string => $state === 'Active' ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('admin.status'))
                    ->placeholder('All')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only')
                    ->columnSpan(1),
            ])
            ->filtersFormColumns(2)
            ->actions(AdminUi::recordActions())
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    AdminUi::exportCsvBulkAction('Export Conditions', [
                        'name' => 'Name',
                        'slug' => 'Slug',
                        'bg_color' => 'Background Color',
                        'text_color' => 'Text Color',
                        'is_active' => 'Active',
                        'sort_order' => 'Sort Order',
                    ]),
                    Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Conditions')
                        ->modalDescription('Are you sure you want to delete these conditions? Conditions that are still in use by products cannot be deleted.')
                        ->action(function ($records): void {
                            $records->each(function ($record) {
                                if ($record->products()->count() > 0) {
                                    \Filament\Notifications\Notification::make()
                                        ->danger()
                                        ->title("Cannot delete \"{$record->name}\"")
                                        ->body("{$record->products()->count()} product(s) still use this condition. Reassign them first.")
                                        ->send();
                                    $this->halt();
                                }
                                $record->delete();
                            });
                        }),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->emptyStateIcon('heroicon-o-tag')
            ->emptyStateHeading('No conditions configured yet')
            ->emptyStateDescription('Add part conditions like New, Used, or Remanufactured to classify products.')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label(__('admin.add_condition'))
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
            'index'  => Pages\ListConditions::route('/'),
            'create' => Pages\CreateCondition::route('/create'),
            'view'   => Pages\ViewCondition::route('/{record}'),
            'edit'   => Pages\EditCondition::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug'];
    }
}
