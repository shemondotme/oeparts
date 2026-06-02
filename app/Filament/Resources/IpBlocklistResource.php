<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IpBlocklistResource\Pages;
use App\Models\IpBlocklist;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IpBlocklistResource extends Resource
{
    protected static ?string $model = IpBlocklist::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-no-symbol';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function getNavigationSort(): ?int
    {
        return 110;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'ip_address';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Block Details')
                    ->schema([
                        Forms\Components\TextInput::make('ip_address')
                            ->label('IP Address')
                            ->required()
                            ->maxLength(45),
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->rows(3)
                            ->nullable(),
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expires At')
                            ->nullable()
                            ->helperText('Leave empty for permanent block'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('reason')
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('blocker.name')
                    ->label('Blocked By')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                Tables\Filters\Filter::make('expired')
                    ->label('Expired')
                    ->query(fn (Builder $query): Builder => $query->where('expires_at', '<', now())->whereNotNull('expires_at')),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped();
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListIpBlocklists::route('/'),
            'create' => Pages\CreateIpBlocklist::route('/create'),
            'edit'   => Pages\EditIpBlocklist::route('/{record}/edit'),
        ];
    }
}
