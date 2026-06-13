<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Filament\Support\AdminUi;
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
                Actions\CreateAction::make(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ]);
    }
}
