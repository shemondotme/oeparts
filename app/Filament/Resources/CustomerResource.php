<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Filament\Support\AdminUi;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\GdprExportService;
use Filament\Forms;
use Filament\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Response;

class CustomerResource extends Resource
{
    protected static ?string $model = User::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-users';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Customers';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'name';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'xl' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 2])
                            ->schema([
                                Section::make('Contact Details')
                                    ->description('Primary account information for this customer.')
                                    ->icon('heroicon-o-user')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Full Name')
                                            ->placeholder('e.g. Jan de Vries')
                                            ->helperText('Customer\'s display name as shown across the platform.')
                                            ->required()
                                            ->maxLength(200),
                                        Forms\Components\TextInput::make('email')
                                            ->label('Email Address')
                                            ->email()
                                            ->placeholder('e.g. jan@bedrijf.nl')
                                            ->helperText('Used for order notifications, password resets, and login. Must be unique.')
                                            ->required()
                                            ->maxLength(255)
                                            ->unique(ignoreRecord: true),
                                        Forms\Components\TextInput::make('phone')
                                            ->label('Phone Number')
                                            ->tel()
                                            ->placeholder('e.g. +31 6 1234 5678')
                                            ->helperText('Optional contact number for delivery or order issues.')
                                            ->nullable()
                                            ->maxLength(30),
                                    ])
                                    ->columns(2),
                            ]),

                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Account Preferences')
                                    ->icon('heroicon-o-cog-6-tooth')
                                    ->description('Communication preferences and account settings for this customer.')
                                    ->schema([
                                        Forms\Components\Toggle::make('is_active')
                                            ->label('Account Active')
                                            ->helperText('Deactivated customers cannot sign in or place orders.')
                                            ->default(true),
                                        Forms\Components\Select::make('preferred_locale')
                                            ->label('Preferred Language')
                                            ->options(AdminUi::LOCALES)
                                            ->native(false)
                                            ->helperText('Determines the default storefront language when this customer logs in.')
                                            ->nullable(),
                                        Forms\Components\TextInput::make('timezone')
                                            ->label('Timezone')
                                            ->placeholder('e.g. Europe/Berlin')
                                            ->helperText('Used for order timestamps and email scheduling. Defaults to UTC if not set.')
                                            ->nullable()
                                            ->maxLength(50),
                                        Forms\Components\Toggle::make('prefers_order_notifications')
                                            ->label('Order Notifications')
                                            ->helperText('Receive emails for order status changes (paid, shipped, delivered).')
                                            ->default(true),
                                        Forms\Components\Toggle::make('prefers_email_notifications')
                                            ->label('Email Notifications')
                                            ->helperText('Receive general account and order-related email notifications.')
                                            ->default(true),
                                        Forms\Components\Toggle::make('prefers_promotional_emails')
                                            ->label('Promotional Emails')
                                            ->helperText('Receive marketing emails, newsletters, and promotional offers.')
                                            ->default(true),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->modifyQueryUsing(function ($query) {
                $paidStatuses = [
                    OrderStatus::Paid->value,
                    OrderStatus::Processing->value,
                    OrderStatus::Shipped->value,
                    OrderStatus::Delivered->value,
                ];

                return $query
                    ->withCount('orders')
                    ->withSum(['orders' => fn ($q) => $q->whereIn('status', $paidStatuses)], 'grand_total')
                    ->withAvg(['orders' => fn ($q) => $q->whereIn('status', $paidStatuses)], 'grand_total')
                    ->with(['orders' => fn ($q) => $q->select(['id', 'user_id', 'created_at'])->latest()->limit(1)]);
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium)
                    ->description(fn (User $record): ?string => $record->email)
                    ->limit(28),
                AdminUi::copyableColumn('email', 'Email', 'Email copied')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Orders')
                    ->counts('orders')
                    ->fontMono()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('total_spent')
                    ->label('Total Spent')
                    ->alignEnd()
                    ->fontMono()
                    ->getStateUsing(fn (User $record): string => format_money($record->orders_sum_grand_total ?? 0))
                    // 'total_spent' is not a real column — default sort threw
                    // "Unknown column in ORDER BY". Order by the withSum alias.
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('orders_sum_grand_total', $direction))
                    ->weight(FontWeight::Medium),
                Tables\Columns\TextColumn::make('avg_order_value')
                    ->label('Avg Order')
                    ->alignEnd()
                    ->fontMono()
                    ->getStateUsing(fn (User $record): string => ($record->orders_avg_grand_total ?? 0) > 0 ? format_money($record->orders_avg_grand_total) : '—')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('orders_avg_grand_total', $direction))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_order_date')
                    ->label('Last Order')
                    ->getStateUsing(fn (User $record): ?string => $record->orders->first()?->created_at?->diffForHumans())
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                        \App\Models\Order::select('created_at')
                            ->whereColumn('user_id', 'users.id')
                            ->latest()
                            ->limit(1),
                        $direction,
                    ))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('segment')
                    ->label('Segment')
                    ->badge()
                    ->getStateUsing(function (User $record): string {
                        $orderCount = $record->orders_count ?? 0;
                        $totalSpent = $record->orders_sum_grand_total ?? 0;

                        if ($orderCount >= 10 && bccomp((string) $totalSpent, '1000', 2) >= 0) {
                            return 'VIP';
                        }
                        if ($orderCount >= 3) {
                            return 'Repeat';
                        }
                        if ($orderCount === 0) {
                            return 'Prospect';
                        }

                        return 'Regular';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'VIP' => 'warning',
                        'Repeat' => 'success',
                        'Prospect' => 'info',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime('M j, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('is_active')
                    ->label('Active')
                    ->badge()
                    ->alignCenter()
                    ->getStateUsing(fn (User $record): string => $record->is_active ? 'Active' : 'Inactive')
                    ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'gray')
                    ->icon(fn (string $state): string => $state === 'Active' ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Account Status')
                    ->placeholder('All')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only')
                    ->columnSpan(1),
                Tables\Filters\Filter::make('created_at')
                    ->label('Registration Date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Registered After')
                            ->placeholder('Select start date'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Registered Before')
                            ->placeholder('Select end date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    })
                    ->columns(2)
                    ->columnSpan(2),
            ])
            ->filtersFormColumns(2)
            ->actions(AdminUi::recordActions(after: [
                Actions\Action::make('resetPassword')
                    ->label('Reset Password')
                    ->icon('heroicon-o-key')
                    ->authorize('update')
                    ->requiresConfirmation()
                    ->modalHeading('Reset Customer Password')
                    ->modalDescription('Set a new password for this customer. They will need to use this new password on their next login.')
                    ->form([
                        Forms\Components\TextInput::make('new_password')
                            ->label('New Password')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->rules(['regex:/^(?=.*[A-Z])(?=.*\d).+$/'])
                            ->placeholder('Enter a strong password')
                            ->helperText('Must contain at least one uppercase letter and one number. Minimum 8 characters.'),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->update(['password' => $data['new_password']]);

                        Notification::make()->title('Password reset successfully')->success()->send();
                    }),
                Actions\Action::make('sendEmail')
                    ->label('Send Email')
                    ->icon('heroicon-o-envelope')
                    ->color('info')
                    ->authorize('update')
                    ->url(fn (User $record): string => "mailto:{$record->email}")
                    ->openUrlInNewTab(),
                Actions\Action::make('exportGdprData')
                    ->label('Export Data (GDPR)')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->authorize('update')
                    ->action(function (User $record) {
                        $data = app(GdprExportService::class)->exportForUser($record);

                        ActivityLog::create([
                            'admin_id' => auth('admin')->id(),
                            'action' => 'gdpr_export',
                            'model_type' => User::class,
                            'model_id' => $record->id,
                            'old_values' => [],
                            'new_values' => ['description' => "Exported GDPR data for {$record->email}"],
                            'ip_address' => request()->ip(),
                        ]);

                        $filename = "gdpr-export-{$record->id}-" . now()->format('Y-m-d-His') . '.json';

                        return Response::streamDownload(
                            fn () => print(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
                            $filename,
                            ['Content-Type' => 'application/json']
                        );
                    }),
                Actions\Action::make('toggleActive')
                    ->label(fn (User $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (User $record): string => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (User $record): string => $record->is_active ? 'danger' : 'success')
                    ->authorize('update')
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record): string => $record->is_active ? 'Deactivate Customer' : 'Activate Customer')
                    ->modalDescription(fn (User $record): ?string => $record->is_active
                        ? 'This customer will be unable to sign in or place orders. Existing orders will not be affected.'
                        : 'Restore this customer\'s account access and order capabilities.')
                    ->action(function (User $record): void {
                        $record->is_active = ! $record->is_active;
                        $record->save();

                        Notification::make()
                            ->title($record->is_active ? 'Customer activated' : 'Customer deactivated')
                            ->success()
                            ->send();
                    }),
            ]))
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    AdminUi::exportCsvBulkAction('Export Customers', [
                        'name' => 'Name',
                        'email' => 'Email',
                        'phone' => 'Phone',
                        'orders_count' => 'Orders',
                        'total_spent' => 'Total Spent',
                        'created_at' => 'Registered',
                        'is_active' => 'Active',
                    ]),
                    // No bulk delete: account erasure is an individual,
                    // deliberate act (GDPR flow) — single delete remains.
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-users')
            ->emptyStateHeading('No customers found')
            ->emptyStateDescription('Customers will appear here after they register on the storefront. You can also import customers via CSV.')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label('Add Customer')
                    ->url(static::getUrl('create'))
                    ->icon('heroicon-o-plus')
                    ->button(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OrdersRelationManager::class,
            RelationManagers\AddressesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view'   => Pages\ViewCustomer::route('/{record}'),
            'edit'   => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone'];
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Email' => $record->email,
            'Status' => $record->is_active ? 'Active' : 'Inactive',
        ];
    }
}
