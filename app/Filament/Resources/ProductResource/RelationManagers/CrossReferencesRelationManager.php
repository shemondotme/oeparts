<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Filament\Support\AdminUi;
use App\Services\OemNormalizerService;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CrossReferencesRelationManager extends RelationManager
{
    protected static string $relationship = 'crossReferences';

    protected static ?string $recordTitleAttribute = 'cross_oem_number';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('cross_oem_number')
                    ->label('Cross OEM Number')
                    ->required()
                    ->maxLength(100)
                    ->extraAttributes(['inputmode' => 'text', 'autocapitalize' => 'characters']),
            ]);
    }

    public function table(Table $table): Table
    {
        return AdminUi::configureTable($table)->recordTitleAttribute('cross_oem_number')
            ->columns([
                Tables\Columns\TextColumn::make('cross_oem_number')
                    ->label('Cross OEM Number')
                    ->copyable()
                    ->copyMessage('OEM number copied')
                    ->fontMono()
                    ->weight('medium')
                    ->extraAttributes(['class' => 'oem-number']),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime('M j, Y'),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->mutateFormDataUsing(fn (array $data): array => self::withNormalizedCrossOem($data)),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->mutateFormDataUsing(fn (array $data): array => self::withNormalizedCrossOem($data)),
                Actions\DeleteAction::make(),
            ]);
    }

    /**
     * Every other place that creates a ProductCrossReference (ProductImportService,
     * the e2e fixture seeders, the factory) sets this explicitly — this was the
     * only path that didn't, and normalized_cross_oem has no default value at
     * the DB level, so every cross-reference added through the admin UI failed
     * with a raw 500 (SearchService's cross-reference lookup also depends on
     * this column actually being populated and normalized the same way
     * Product::normalized_oem is, not just a raw copy of cross_oem_number).
     */
    private static function withNormalizedCrossOem(array $data): array
    {
        if (isset($data['cross_oem_number'])) {
            $data['normalized_cross_oem'] = app(OemNormalizerService::class)->normalize($data['cross_oem_number']);
        }

        return $data;
    }
}
