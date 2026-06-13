<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AbandonedCartResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\AbandonedCart;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class AbandonedCartResource extends Resource
{
    protected static ?string $model = AbandonedCart::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-shopping-cart';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('recovery_email_sent', false)->where('created_at', '>=', now()->subHours(24))->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Marketing';
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
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
                                Section::make('Cart Snapshot')
                                    ->icon('heroicon-o-shopping-bag')
                                    ->description('Raw cart data captured at the time of abandonment including items and totals.')
                                    ->schema([
                                        Forms\Components\Textarea::make('cart_snapshot')
                                            ->label('Cart Data (JSON)')
                                            ->hiddenLabel()
                                            ->json()
                                            ->readOnly()
                                            ->rows(12)
                                            ->columnSpanFull()
                                            ->helperText('This is the raw JSON snapshot of the cart contents when the customer left.'),
                                    ]),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Customer & Contact')
                                    ->icon('heroicon-o-user')
                                    ->description('Customer information associated with this abandoned cart.')
                                    ->schema([
                                        Forms\Components\Select::make('user_id')
                                            ->label('Registered Customer')
                                            ->relationship('user', 'name')
                                            ->disabled()
                                            ->placeholder('Guest Customer')
                                            ->helperText('Linked customer account if they were logged in.'),
                                        Forms\Components\TextInput::make('guest_email')
                                            ->label('Guest Email')
                                            ->email()
                                            ->readOnly()
                                            ->placeholder('Not provided')
                                            ->helperText('Email entered during checkout for guest customers.'),
                                    ]),

                                Section::make('Activity & Recovery')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->description('Track customer activity and recovery email status.')
                                    ->schema([
                                        Forms\Components\DateTimePicker::make('last_active_at')
                                            ->label('Last Active')
                                            ->disabled()
                                            ->helperText('When the customer was last active on the site.'),
                                        Forms\Components\Toggle::make('recovery_email_sent')
                                            ->label('Recovery Email Sent')
                                            ->disabled()
                                            ->helperText('Whether a recovery email has been sent for this cart.'),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
            Tables\Columns\TextColumn::make('user.name')
                ->label('Customer')
                ->searchable()
                ->sortable()
                ->placeholder('Guest Customer')
                ->weight(FontWeight::Medium),
            Tables\Columns\TextColumn::make('guest_email')
                ->label('Email')
                ->searchable()
                ->copyable()
                ->copyMessage('Email copied')
                ->placeholder('—')
                ->limit(30),
                Tables\Columns\TextColumn::make('last_active_at')
                    ->label('Last Active')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('recovery_email_sent')
                    ->label('Recovery Sent')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Sent' : 'No Recovery')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('last_active_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('recovery_email_sent')
                    ->label('Recovery Email Status')
                    ->placeholder('All')
                    ->trueLabel('Recovery Sent')
                    ->falseLabel('No Recovery Sent')
                    ->native(false),
            ])
            ->actions(AdminUi::recordActions(before: [
                Actions\Action::make('sendRecovery')
                    ->label('Send Recovery')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Send Recovery Email')
                    ->modalDescription('Send a cart recovery email to the customer. This will remind them of the items left in their cart.')
                    ->action(function (AbandonedCart $record) {
                        $email = $record->guest_email ?? $record->user?->email;

                        if (!$email) {
                            Notification::make()
                                ->title('No email address')
                                ->body('This abandoned cart has no associated customer or guest email.')
                                ->danger()
                                ->send();
                            return;
                        }

                        dispatch(new \App\Jobs\SendAbandonedCartEmail(
                            email: $email,
                            cartSnapshot: $record->cart_snapshot,
                            customerName: $record->user?->name ?? 'Customer',
                            locale: $record->user?->preferred_locale ?? 'en'
                        ));

                        $record->update(['recovery_email_sent' => true]);

                        Notification::make()
                            ->title('Recovery email sent')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (AbandonedCart $record): bool => (bool) ($record->guest_email ?? $record->user?->email)),
            ]))
        ->bulkActions([
            Actions\BulkActionGroup::make([
                AdminUi::exportCsvBulkAction('Export Carts', [
                    'user.name' => 'Customer',
                    'guest_email' => 'Email',
                    'last_active_at' => 'Last Active',
                    'recovery_email_sent' => 'Recovery Sent',
                    'created_at' => 'Created',
                ]),
                Actions\DeleteBulkAction::make(),
            ]),
        ])
            ->emptyStateIcon('heroicon-o-shopping-cart')
            ->emptyStateHeading('No abandoned carts detected')
            ->emptyStateDescription('Carts abandoned by customers during checkout will appear here. You can send recovery emails to encourage them to complete their order.');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAbandonedCarts::route('/'),
            'view'  => Pages\ViewAbandonedCart::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['guest_email'];
    }
}
