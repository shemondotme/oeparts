<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Models\User;
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
                Section::make('Customer Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(200),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->nullable()
                            ->maxLength(30),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        Forms\Components\Select::make('preferred_locale')
                            ->label('Preferred Language')
                            ->options([
                                'en' => 'English',
                                'de' => 'German',
                                'lt' => 'Lithuanian',
                                'fr' => 'French',
                                'es' => 'Spanish',
                            ])
                            ->nullable(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->limit(25),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Orders')
                    ->counts('orders')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('total_spent')
                    ->label('Total Spent')
                    ->getStateUsing(function (User $record): string {
                        $paidStatuses = [
                            OrderStatus::Paid->value,
                            OrderStatus::Processing->value,
                            OrderStatus::Shipped->value,
                            OrderStatus::Delivered->value,
                        ];

                        $total = $record->orders()
                            ->whereIn('status', $paidStatuses)
                            ->sum('grand_total');

                        return format_money($total);
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime('M j, Y')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
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
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\Action::make('toggleActive')
                    ->label(fn (User $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (User $record): string => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (User $record): string => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $record->is_active = !$record->is_active;
                        $record->save();

                        Notification::make()
                            ->title($record->is_active ? 'Customer activated' : 'Customer deactivated')
                            ->success()
                            ->send();
                    }),
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('exportCsv')
                        ->label('Export CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function ($records): void {
                            $paidStatuses = [
                                OrderStatus::Paid->value,
                                OrderStatus::Processing->value,
                                OrderStatus::Shipped->value,
                                OrderStatus::Delivered->value,
                            ];

                            $ids = $records->pluck('id');
                            $orderCounts = \App\Models\Order::whereIn('user_id', $ids)
                                ->selectRaw('user_id, COUNT(*) as total_orders, SUM(CASE WHEN status IN (\'' . implode("','", $paidStatuses) . '\') THEN grand_total ELSE 0 END) as total_revenue')
                                ->groupBy('user_id')
                                ->get()
                                ->keyBy('user_id');

                            $csv = "Name,Email,Phone,Orders,Total Spent,Registered,Active\n";
                            foreach ($records as $record) {
                                $stats = $orderCounts->get($record->id);
                                $totalOrders = $stats ? (string) $stats->total_orders : '0';
                                $totalRevenue = $stats ? bcadd((string) $stats->total_revenue, '0', 2) : '0.00';

                                $csv .= sprintf(
                                    "%s,%s,%s,%s,%s,%s,%s\n",
                                    $record->name,
                                    $record->email,
                                    $record->phone ?? '',
                                    $totalOrders,
                                    $totalRevenue,
                                    $record->created_at->format('Y-m-d'),
                                    $record->is_active ? 'Yes' : 'No'
                                );
                            }

                            $filename = 'customers_export_' . now()->format('Y-m-d_His') . '.csv';
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

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('created_at', '>=', now()->subDays(7))->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }
}
