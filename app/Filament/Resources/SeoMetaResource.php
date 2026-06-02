<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SeoMetaResource\Pages;
use App\Models\SeoMeta;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SeoMetaResource extends Resource
{
    protected static ?string $model = SeoMeta::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-globe-alt';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function getNavigationSort(): ?int
    {
        return 40;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'meta_title';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('SEO Metadata')
                    ->schema([
                        Forms\Components\TextInput::make('metable_type')
                            ->label('Resource Type')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('metable_id')
                            ->label('Resource ID')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('meta_title')
                            ->label('Meta Title')
                            ->maxLength(255)
                            ->nullable(),
                        Forms\Components\Textarea::make('meta_description')
                            ->label('Meta Description')
                            ->rows(3)
                            ->nullable(),
                        Forms\Components\TextInput::make('canonical_url')
                            ->label('Canonical URL')
                            ->maxLength(500)
                            ->nullable()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('og_title')
                            ->label('OG Title')
                            ->maxLength(255)
                            ->nullable(),
                        Forms\Components\Textarea::make('og_description')
                            ->label('OG Description')
                            ->rows(2)
                            ->nullable(),
                        Forms\Components\Select::make('robots')
                            ->options([
                                'index, follow' => 'Index, Follow',
                                'noindex, follow' => 'No Index, Follow',
                                'index, nofollow' => 'Index, No Follow',
                                'noindex, nofollow' => 'No Index, No Follow',
                            ])
                            ->nullable(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('metable_type')
                    ->label('Type')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('metable_id')
                    ->label('ID')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('meta_title')
                    ->label('Meta Title')
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\TextColumn::make('robots')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('metable_type')
                    ->label('Type')
                    ->options(fn (): array => SeoMeta::distinct()->pluck('metable_type', 'metable_type')->toArray()),
                Tables\Filters\SelectFilter::make('robots')
                    ->options([
                        'index, follow' => 'Index, Follow',
                        'noindex, follow' => 'No Index, Follow',
                    ]),
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
            'index' => Pages\ListSeoMetas::route('/'),
            'edit'  => Pages\EditSeoMeta::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
