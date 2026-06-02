<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\SequenceService;
use Filament\Forms;
use Filament\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $slug = 'filament/orders';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-shopping-bag';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Commerce';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'order_number';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order Information')
                    ->schema([
                        Forms\Components\TextInput::make('order_number')
                            ->label('Order Number')
                            ->required()
                            ->maxLength(30)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->label('Customer'),
                        Forms\Components\TextInput::make('guest_email')
                            ->email()
                            ->nullable()
                            ->label('Guest Email'),
                    ])->columns(2),

                Section::make('Status & Payment')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options(OrderStatus::class)
                            ->required()
                            ->default(OrderStatus::Pending),
                        Forms\Components\Select::make('payment_method')
                            ->options(PaymentMethod::class)
                            ->required(),
                        Forms\Components\Select::make('payment_status')
                            ->options(PaymentStatus::class)
                            ->required()
                            ->default(PaymentStatus::Pending),
                        Forms\Components\TextInput::make('payment_reference')
                            ->nullable()
                            ->maxLength(100),
                    ])->columns(2),

                Section::make('Financial')
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->numeric()
                            ->prefix('€')
                            ->required(),
                        Forms\Components\TextInput::make('discount_amount')
                            ->numeric()
                            ->prefix('€')
                            ->default(0),
                        Forms\Components\TextInput::make('shipping_cost')
                            ->numeric()
                            ->prefix('€')
                            ->required(),
                        Forms\Components\TextInput::make('vat_amount')
                            ->numeric()
                            ->prefix('€')
                            ->required(),
                        Forms\Components\TextInput::make('grand_total')
                            ->numeric()
                            ->prefix('€')
                            ->required(),
                    ])->columns(3),

                Section::make('Shipping Address')
                    ->schema([
                        Forms\Components\TextInput::make('shipping_name')
                            ->required()
                            ->maxLength(200),
                        Forms\Components\TextInput::make('shipping_address_line1')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('shipping_city')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('shipping_postal_code')
                            ->required()
                            ->maxLength(20),
                        Forms\Components\TextInput::make('shipping_country_code')
                            ->required()
                            ->maxLength(2),
                    ])->columns(2),

                Section::make('B2B')
                    ->schema([
                        Forms\Components\Toggle::make('is_b2b')
                            ->default(false),
                        Forms\Components\TextInput::make('company_name')
                            ->nullable()
                            ->maxLength(200),
                        Forms\Components\TextInput::make('vat_number')
                            ->nullable()
                            ->maxLength(50),
                        Forms\Components\Toggle::make('vat_exempt')
                            ->default(false),
                    ])->columns(2),

                Section::make('Additional')
                    ->schema([
                        Forms\Components\Textarea::make('customer_note')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('tracking_number')
                            ->nullable()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('carrier')
                            ->nullable()
                            ->maxLength(100),
                        Forms\Components\Toggle::make('urgent_processing')
                            ->default(false),
                        Forms\Components\TextInput::make('invoice_number')
                            ->nullable()
                            ->maxLength(30),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Order number copied')
                    ->extraAttributes(['class' => 'oem-number'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->getStateUsing(fn (Order $record): string => $record->shipping_name ?? $record->user?->name ?? $record->guest_email ?? '—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->where('shipping_name', 'like', "%{$search}%")
                                ->orWhere('guest_email', 'like', "%{$search}%")
                                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
                        });
                    })
                    ->limit(25),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (OrderStatus $state): string => match ($state) {
                        OrderStatus::Pending         => 'warning',
                        OrderStatus::Paid            => 'info',
                        OrderStatus::Processing      => 'primary',
                        OrderStatus::Shipped         => 'success',
                        OrderStatus::Delivered       => 'success',
                        OrderStatus::Cancelled       => 'danger',
                        OrderStatus::RefundRequested => 'warning',
                        OrderStatus::Refunded        => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->color(fn (PaymentStatus $state): string => match ($state) {
                        PaymentStatus::Pending  => 'warning',
                        PaymentStatus::Paid     => 'success',
                        PaymentStatus::Failed   => 'danger',
                        PaymentStatus::Refunded => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Total')
                    ->getStateUsing(fn (Order $record): string => format_money($record->grand_total))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M j, Y H:i')
                    ->since()
                    ->sortable(),
                Tables\Columns\IconColumn::make('urgent_processing')
                    ->label('Urgent')
                    ->boolean()
                    ->color('danger')
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(OrderStatus::class)
                    ->multiple(),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options(PaymentMethod::class),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options(PaymentStatus::class),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
                Tables\Filters\Filter::make('country')
                    ->form([
                        Forms\Components\TextInput::make('country_code')
                            ->label('Country Code')
                            ->maxLength(2),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['country_code'],
                            fn ($q, $code) => $q->where('shipping_country_code', strtoupper($code))
                        );
                    }),
                Tables\Filters\TernaryFilter::make('is_b2b')
                    ->label('B2B Only')
                    ->nullable(),
                Tables\Filters\TernaryFilter::make('urgent_processing')
                    ->label('Urgent Processing')
                    ->nullable(),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\Action::make('changeStatus')
                    ->label('Change Status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->schema([
                        Forms\Components\Select::make('new_status')
                            ->label('New Status')
                            ->options(OrderStatus::class)
                            ->required(),
                        Forms\Components\Textarea::make('note')
                            ->label('Note')
                            ->required()
                            ->rows(3)
                            ->placeholder('Reason for status change...'),
                    ])
                    ->action(function (Order $record, array $data): void {
                        $oldStatus = $record->status;
                        $record->status = OrderStatus::from($data['new_status']);
                        $record->save();

                        OrderStatusHistory::create([
                            'order_id'   => $record->id,
                            'admin_id'   => auth('admin')->id(),
                            'old_status' => $oldStatus->value,
                            'new_status' => $data['new_status'],
                            'note'       => $data['note'],
                        ]);

                        Notification::make()
                            ->title('Order status updated')
                            ->success()
                            ->send();
                    }),
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('markProcessing')
                        ->label('Mark as Processing')
                        ->icon('heroicon-o-arrow-path')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            foreach ($records as $record) {
                                if ($record->status === OrderStatus::Paid) {
                                    $record->status = OrderStatus::Processing;
                                    $record->save();

                                    OrderStatusHistory::create([
                                        'order_id'   => $record->id,
                                        'admin_id'   => auth('admin')->id(),
                                        'old_status' => OrderStatus::Paid->value,
                                        'new_status' => OrderStatus::Processing->value,
                                        'note'       => 'Bulk status update',
                                    ]);
                                }
                            }

                            Notification::make()
                                ->title('Orders marked as processing')
                                ->success()
                                ->send();
                        }),
                    Actions\BulkAction::make('exportCsv')
                        ->label('Export CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function ($records): void {
                            $records->load('user');

                            $csv = "Order Number,Customer,Status,Payment,Total,Date\n";
                            foreach ($records as $record) {
                                $customer = $record->shipping_name ?? $record->user?->name ?? $record->guest_email ?? '—';
                                $csv .= sprintf(
                                    "%s,%s,%s,%s,%s,%s\n",
                                    $record->order_number,
                                    $customer,
                                    $record->status->value,
                                    $record->payment_status->value,
                                    bcadd((string) $record->grand_total, '0', 2),
                                    $record->created_at->format('Y-m-d H:i')
                                );
                            }

                            $filename = 'orders_export_' . now()->format('Y-m-d_His') . '.csv';
                            $path = storage_path('app/exports/' . $filename);
                            if (!is_dir(storage_path('app/exports'))) {
                                mkdir(storage_path('app/exports'), 0755, true);
                            }
                            file_put_contents($path, $csv);

                            $url = route('admin.export.download', ['filename' => $filename]);

                            Notification::make()
                                ->title('CSV exported')
                                ->body("File: {$filename}")
                                ->success()
                                ->actions([
                                    NotificationAction::make('download')
                                        ->label('Download')
                                        ->url($url)
                                        ->openUrlInNewTab(),
                                ])
                                ->send();
                        }),
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\OrderResource\RelationManagers\OrderItemsRelationManager::class,
            \App\Filament\Resources\OrderResource\RelationManagers\OrderNotesRelationManager::class,
            \App\Filament\Resources\OrderResource\RelationManagers\OrderStatusHistoryRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view'   => Pages\ViewOrder::route('/{record}'),
            'edit'   => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', OrderStatus::Pending)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $count = static::getModel()::where('status', OrderStatus::Pending)->count();

        return $count > 10 ? 'danger' : 'warning';
    }
}
