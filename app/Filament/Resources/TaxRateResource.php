<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaxRateResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\TaxRate;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class TaxRateResource extends Resource
{
    protected static ?string $model = TaxRate::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-receipt-percent';
    }

    public static function getNavigationLabel(): string
    {
        return 'Tax Rates';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Commerce';
    }

    public static function getNavigationSort(): ?int
    {
        return 31; // just after Shipping Zones (30)
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'country_name';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Country Tax Rate')
                    ->description('Only takes effect once "Country-Based VAT" is enabled on the Tax Settings page.')
                    ->schema([
                        Forms\Components\TextInput::make('country_code')
                            ->label('Country Code')
                            ->placeholder('e.g. DE')
                            ->required()
                            ->maxLength(2)
                            ->minLength(2)
                            ->unique(ignoreRecord: true)
                            ->rules(['regex:/^[A-Za-z]{2}$/'])
                            ->helperText('ISO 3166-1 alpha-2 code (e.g. DE, LT, FR).')
                            ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper($state) : $state),

                        Forms\Components\TextInput::make('country_name')
                            ->label('Country Name')
                            ->placeholder('e.g. Germany')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('rate')
                            ->label('VAT / Tax Rate (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%')
                            ->required(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Inactive rates are skipped — the flat default rate applies instead.')
                            ->default(true),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->placeholder('e.g. Source/date confirmed, reduced-rate carve-outs, etc.')
                            ->columnSpanFull()
                            ->rows(2),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('country_code')
                    ->label('Code')
                    ->fontMono()
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('country_name')
                    ->label('Country')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('rate')
                    ->label('Rate')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2).'%')
                    ->fontMono()
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->alignCenter()
                    ->getStateUsing(fn (TaxRate $record): string => $record->is_active ? 'Active' : 'Inactive')
                    ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'gray')
                    ->icon(fn (string $state): string => $state === 'Active' ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only')
                    ->columnSpan(1),
            ])
            ->actions(AdminUi::recordActions())
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    AdminUi::exportCsvBulkAction('Export Tax Rates', [
                        'country_code' => 'Code',
                        'country_name' => 'Country',
                        'rate' => 'Rate',
                        'is_active' => 'Active',
                    ]),
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('country_name', 'asc')
            ->emptyStateIcon('heroicon-o-receipt-percent')
            ->emptyStateHeading('No country tax rates configured yet')
            ->emptyStateDescription('Add a rate per country to enable destination-based VAT — see Tax Settings to turn the feature on.')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label('Add Tax Rate')
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
            'index' => Pages\ListTaxRates::route('/'),
            'create' => Pages\CreateTaxRate::route('/create'),
            'view' => Pages\ViewTaxRate::route('/{record}'),
            'edit' => Pages\EditTaxRate::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['country_code', 'country_name'];
    }
}
