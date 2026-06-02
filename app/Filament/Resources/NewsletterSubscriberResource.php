<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsletterSubscriberResource\Pages;
use App\Models\NewsletterSubscriber;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NewsletterSubscriberResource extends Resource
{
    protected static ?string $model = NewsletterSubscriber::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-envelope';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Marketing';
    }

    public static function getNavigationSort(): ?int
    {
        return 40;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'email';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Subscriber Details')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('lang')
                            ->label('Language')
                            ->options([
                                'en' => 'English',
                                'de' => 'German',
                                'lt' => 'Lithuanian',
                                'fr' => 'French',
                                'es' => 'Spanish',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('ip_address')
                            ->label('IP Address')
                            ->maxLength(45)
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        Forms\Components\DateTimePicker::make('subscribed_at')
                            ->label('Subscribed At')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\DateTimePicker::make('unsubscribed_at')
                            ->label('Unsubscribed At')
                            ->nullable(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('lang')
                    ->label('Lang')
                    ->badge()
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('subscribed_at')
                    ->label('Subscribed')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('unsubscribed_at')
                    ->label('Unsubscribed')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                Tables\Filters\SelectFilter::make('lang')
                    ->options([
                        'en' => 'English',
                        'de' => 'German',
                        'lt' => 'Lithuanian',
                        'fr' => 'French',
                        'es' => 'Spanish',
                    ]),
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
            ->defaultSort('subscribed_at', 'desc')
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
            'index'  => Pages\ListNewsletterSubscribers::route('/'),
            'create' => Pages\CreateNewsletterSubscriber::route('/create'),
            'view'   => Pages\ViewNewsletterSubscriber::route('/{record}'),
            'edit'   => Pages\EditNewsletterSubscriber::route('/{record}/edit'),
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
