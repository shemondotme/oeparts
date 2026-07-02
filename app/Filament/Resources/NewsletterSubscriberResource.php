<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsletterSubscriberResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\NewsletterSubscriber;
use Filament\Forms;
use Filament\Actions;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
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
                Grid::make(['default' => 1, 'xl' => 3])
                    ->columnSpanFull()
                    ->schema([
                        // ─── Main column ──────────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 2])
                            ->schema([
                                Section::make('Subscriber Details')
                                    ->icon('heroicon-o-envelope')
                                    ->description('Core subscriber information and language preference.')
                                    ->schema([
                                        Forms\Components\TextInput::make('email')
                                            ->label('Email Address')
                                            ->email()
                                            ->required()
                                            ->disabled(fn (string $operation): bool => $operation === 'edit')
                                            ->maxLength(255)
                                            ->helperText('Email cannot be changed after subscription.'),
                                        Forms\Components\Select::make('lang')
                                            ->label('Preferred Language')
                                            ->options(AdminUi::LOCALES)
                                            ->native(false)
                                            ->required()
                                            ->helperText('Language used for newsletter content sent to this subscriber.'),
                                    ])
                                    ->columns(2),

                                Section::make('Connection Details')
                                    ->icon('heroicon-o-computer-desktop')
                                    ->description('Technical information captured during subscription.')
                                    ->schema([
                                        AdminUi::readOnlyField('ip_address', 'IP Address', 'IP address at the time of subscription.'),
                                    ]),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Status & Timing')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->description('Subscription status and important dates.')
                                    ->schema([
                                        Forms\Components\Toggle::make('is_active')
                                            ->label('Subscriber Active')
                                            ->helperText('Inactive subscribers will not receive newsletter campaigns.')
                                            ->default(true),
                                        Forms\Components\DateTimePicker::make('subscribed_at')
                                            ->label('Subscribed At')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->helperText('When this subscriber signed up.'),
                                        Forms\Components\DateTimePicker::make('unsubscribed_at')
                                            ->label('Unsubscribed At')
                                            ->nullable()
                                            ->helperText('When this subscriber opted out. Set automatically on unsubscription.'),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->modifyQueryUsing(fn ($query) => $query->withCount('campaignRecipients'))
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),
            Tables\Columns\TextColumn::make('lang')
                ->label('Language')
                ->badge()
                ->formatStateUsing(fn (string $state): string => AdminUi::LOCALES[$state] ?? strtoupper($state))
                ->color('gray')
                ->searchable()
                ->alignCenter(),
                Tables\Columns\TextColumn::make('is_active')
                    ->label('Active')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('subscribed_at')
                    ->label('Subscribed')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('unsubscribed_at')
                    ->label('Unsubscribed')
                    ->dateTime('M j, Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Subscription Status')
                    ->placeholder('All')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only')
                    ->native(false)
                    ->columnSpan(1),
                Tables\Filters\SelectFilter::make('lang')
                    ->label('Preferred Language')
                    ->options(AdminUi::LOCALES)
                    ->native(false)
                    ->helperText('Filter subscribers by their preferred language.')
                    ->columnSpan(1),
            ])
            ->filtersFormColumns(2)
            ->actions(AdminUi::recordActions())
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    AdminUi::impactBulkAction(
                        name: 'bulkToggleActive',
                        label: 'Toggle Active',
                        color: 'warning',
                        icon: 'heroicon-o-arrow-path',
                        action: function ($records) {
                            $firstState = $records->first()->is_active;
                            $allSame = $records->every(fn (NewsletterSubscriber $record) => $record->is_active === $firstState);
                            $newState = $allSame ? !$firstState : true;

                            $records->each(function (NewsletterSubscriber $record) use ($newState) {
                                $record->update([
                                    'is_active' => $newState,
                                    'unsubscribed_at' => $newState ? null : now(),
                                ]);
                            });

                            Notification::make()
                                ->title($records->count() . ' subscribers ' . ($newState ? 'activated' : 'deactivated'))
                                ->success()
                                ->send();
                        },
                    )->authorize('update', NewsletterSubscriber::class),
                AdminUi::exportCsvBulkAction('Export Subscribers', [
                    'email' => 'Email',
                    'lang' => 'Language',
                    'is_active' => 'Active',
                    'subscribed_at' => 'Subscribed',
                    'unsubscribed_at' => 'Unsubscribed',
                ]),
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('subscribed_at', 'desc')
            ->emptyStateIcon('heroicon-o-envelope')
            ->emptyStateHeading('No newsletter subscribers yet')
            ->emptyStateDescription('Subscribers who sign up through the storefront newsletter form will appear here.')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label('Add Subscriber')
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
            'index'  => Pages\ListNewsletterSubscribers::route('/'),
            'create' => Pages\CreateNewsletterSubscriber::route('/create'),
            'view'   => Pages\ViewNewsletterSubscriber::route('/{record}'),
            'edit'   => Pages\EditNewsletterSubscriber::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['email'];
    }
}
