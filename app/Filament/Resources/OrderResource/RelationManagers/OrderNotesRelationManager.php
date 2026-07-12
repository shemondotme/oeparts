<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Filament\Support\AdminUi;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Actions;
use Filament\Tables\Table;

class OrderNotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    protected static ?string $recordTitleAttribute = 'note';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Textarea::make('note')
                    ->label('Note')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->recordTitleAttribute('note')
            ->modifyQueryUsing(fn ($query) => $query->with('admin'))
            ->columns([
                Tables\Columns\TextColumn::make('note')
                    ->label('Note')
                    ->limit(80),
                Tables\Columns\TextColumn::make('admin.name')
                    ->label('Author')
                    ->getStateUsing(fn ($record): string => $record->admin?->name ?? 'System'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M j, Y H:i'),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['admin_id'] = auth('admin')->id();

                        return $data;
                    }),
            ])
            ->actions([
                ...AdminUi::recordActionsWithoutView(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
