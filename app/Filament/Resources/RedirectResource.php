<?php

namespace App\Filament\Resources;

use App\Enums\RedirectType;
use App\Filament\Resources\RedirectResource\Pages;
use App\Models\Redirect;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-arrow-right-end-on-rectangle';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function getNavigationSort(): ?int
    {
        return 50;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'from_url';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Redirect Rule')
                    ->schema([
                        Forms\Components\TextInput::make('from_url')
                            ->label('From URL')
                            ->helperText('e.g. /old-page or /en/old-path')
                            ->required()
                            ->maxLength(500),
                        Forms\Components\TextInput::make('to_url')
                            ->label('To URL')
                            ->helperText('e.g. /new-page or https://example.com')
                            ->required()
                            ->maxLength(500),
                        Forms\Components\Select::make('type')
                            ->options(RedirectType::class)
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        Forms\Components\TextInput::make('hit_count')
                            ->label('Hits')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('from_url')
                    ->label('From')
                    ->searchable()
                    ->copyable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('to_url')
                    ->label('To')
                    ->searchable()
                    ->copyable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (RedirectType $state): string => match ($state) {
                        RedirectType::Permanent => 'success',
                        RedirectType::Temporary => 'warning',
                    }),
                Tables\Columns\TextColumn::make('hit_count')
                    ->label('Hits')
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(RedirectType::class),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
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
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRedirects::route('/'),
            'create' => Pages\CreateRedirect::route('/create'),
            'edit'   => Pages\EditRedirect::route('/{record}/edit'),
        ];
    }
}
