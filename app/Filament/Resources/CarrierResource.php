<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CarrierResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\Carrier;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class CarrierResource extends Resource
{
    protected static ?string $model = Carrier::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-truck';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Commerce';
    }

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Carrier Details')
                    ->description('Configure the shipping carrier and tracking integration.')
                    ->icon('heroicon-o-truck')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('admin.carrier_name'))
                            ->placeholder('e.g. DHL, DPD, GLS, UPS')
                            ->required()
                            ->maxLength(100)
                            ->helperText('Display name shown to admins and customers.'),
                        Forms\Components\TextInput::make('tracking_url')
                            ->label(__('admin.tracking_url_template'))
                            ->placeholder('e.g. https://www.dhl.com/track?trackingNo={tracking_no}')
                            ->helperText('Use {tracking_no} as a placeholder for the actual tracking number. The customer\'s tracking link is built from this template.')
                            ->url()
                            ->maxLength(500)
                            // The column is NOT NULL (migration
                            // 2026_03_26_100006, string('tracking_url', 500)
                            // with no ->nullable()), but this field is
                            // legitimately optional (a carrier without online
                            // tracking is valid) — leaving it blank submits
                            // null, not '', throwing a raw SQLSTATE NOT NULL
                            // constraint failure instead of saving, confirmed
                            // live. Cast null -> '' on dehydrate rather than
                            // forcing every carrier to have a tracking URL.
                            ->dehydrateStateUsing(fn (?string $state): string => $state ?? ''),
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('admin.carrier_active'))
                            ->helperText('Inactive carriers cannot be assigned to new orders.')
                            ->default(true),
                        Forms\Components\TextInput::make('sort_order')
                            ->label(__('admin.display_order'))
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Lower numbers appear first in selection lists.'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->columns([
            Tables\Columns\TextColumn::make('name')
                ->label(__('admin.carrier'))
                ->searchable()
                ->sortable()
                ->weight(FontWeight::Medium),
            Tables\Columns\TextColumn::make('tracking_url')
                ->label(__('admin.tracking_url'))
                ->limit(40)
                ->copyable()
                ->copyMessage('URL copied')
                ->tooltip(fn (Carrier $record): ?string => $record->tracking_url)
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('is_active')
                ->label(__('admin.active'))
                ->badge()
                ->alignCenter()
                ->sortable()
                ->getStateUsing(fn (Carrier $record): string => $record->is_active ? 'Active' : 'Inactive')
                ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'gray')
                ->icon(fn (string $state): string => $state === 'Active' ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
            Tables\Columns\TextColumn::make('sort_order')
                ->label(__('admin.order'))
                ->numeric()
                ->sortable()
                ->fontMono()
                ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('admin.carrier_status'))
                    ->placeholder('All')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),
            ])
            ->actions(AdminUi::recordActionsWithoutView())
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->emptyStateIcon('heroicon-o-truck')
            ->emptyStateHeading('No carriers configured')
            ->emptyStateDescription('Add shipping carriers to enable tracking URLs and order fulfillment.')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label(__('admin.add_carrier'))
                    ->url(static::getUrl('create'))
                    ->icon('heroicon-o-plus')
                    ->button(),
            ])
        ->bulkActions([
            Actions\BulkActionGroup::make([
                AdminUi::exportCsvBulkAction('Export Carriers', [
                    'name' => 'Carrier',
                    'tracking_url' => 'Tracking URL',
                    'is_active' => 'Active',
                    'sort_order' => 'Sort Order',
                ]),
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
            'index'  => Pages\ListCarriers::route('/'),
            'create' => Pages\CreateCarrier::route('/create'),
            'view'   => Pages\ViewCarrier::route('/{record}'),
            'edit'   => Pages\EditCarrier::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }
}
