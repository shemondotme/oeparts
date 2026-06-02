<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FaqResource\Pages;
use App\Models\Faq;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-question-mark-circle';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Content';
    }

    public static function getNavigationSort(): ?int
    {
        return 40;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'question';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('FAQ Entry')
                    ->schema([
                        Forms\Components\TextInput::make('question')
                            ->label('Question (JSON)')
                            ->helperText('e.g. {"en": "How long does shipping take?", "de": "Wie lange dauert der Versand?"}')
                            ->required(),
                        Forms\Components\Textarea::make('answer')
                            ->label('Answer (JSON)')
                            ->helperText('HTML answer per language')
                            ->rows(5)
                            ->nullable(),
                        Forms\Components\Select::make('category')
                            ->label('Category')
                            ->options([
                                'shipping'  => 'Shipping',
                                'ordering'  => 'Ordering',
                                'returns'   => 'Returns',
                                'payment'   => 'Payment',
                                'products'  => 'Products',
                                'general'   => 'General',
                            ])
                            ->nullable(),
                    ]),
                Section::make('Settings')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('question')
                    ->label('Question')
                    ->getStateUsing(fn (Faq $record): string => is_array($record->question) ? ($record->question['en'] ?? $record->question[array_key_first($record->question)] ?? '—') : ($record->question ?? '—'))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->where('question->en', 'like', "%{$search}%")
                                ->orWhere('question->de', 'like', "%{$search}%");
                        });
                    })
                    ->limit(50),
                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sort')
                    ->alignCenter()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'shipping' => 'Shipping',
                        'ordering' => 'Ordering',
                        'returns'  => 'Returns',
                        'payment'  => 'Payment',
                        'products' => 'Products',
                        'general'  => 'General',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order')
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
            'index'  => Pages\ListFaqs::route('/'),
            'create' => Pages\CreateFaq::route('/create'),
            'view'   => Pages\ViewFaq::route('/{record}'),
            'edit'   => Pages\EditFaq::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('is_active', true)->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
