<?php

namespace App\Filament\Resources\ShippingZoneResource\RelationManagers;

use App\Filament\Support\AdminUi;
use App\Services\ViesService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Actions;
use Filament\Tables\Table;

class CountriesRelationManager extends RelationManager
{
    protected static string $relationship = 'countries';

    protected static ?string $recordTitleAttribute = 'country_name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('country_code')
                    ->label('Country')
                    ->options(config('countries', []))
                    ->searchable()
                    ->required()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $countries = config('countries', []);
                        $set('country_name', $countries[$state] ?? $state);
                    })
                    ->reactive(),
                Forms\Components\TextInput::make('country_name')
                    ->label('Country Name')
                    ->maxLength(100)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->recordTitleAttribute('country_name')
            ->columns([
                Tables\Columns\TextColumn::make('country_code')
                    ->label('Code')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('country_name')
                    ->label('Country')
                    ->searchable()
                    ->weight(FontWeight::Medium),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('Add Country'),
                Actions\Action::make('addAllEuCountries')
                    ->label('Add All EU/EEA Countries')
                    ->icon('heroicon-o-globe-europe-africa')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Add all EU/EEA countries to this zone?')
                    ->modalDescription('Adds every country from the standard EU/EEA list (the same list used for VAT and manufacturer country) that is not already assigned to this zone. Existing countries and other zones are untouched.')
                    ->action(function (): void {
                        $existing = $this->getOwnerRecord()->countries()->pluck('country_code')->all();
                        $added = 0;

                        foreach (ViesService::getEuCountries() as $code => $name) {
                            if (in_array($code, $existing, true)) {
                                continue;
                            }

                            $this->getOwnerRecord()->countries()->create([
                                'country_code' => $code,
                                'country_name' => $name,
                            ]);
                            $added++;
                        }

                        Notification::make()
                            ->title($added > 0 ? "{$added} countries added" : 'Nothing to add')
                            ->body($added > 0 ? null : 'This zone already has every EU/EEA country.')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                ...AdminUi::recordActionsWithoutView(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
