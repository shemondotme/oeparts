<?php

namespace App\Filament\Resources;

use App\Enums\RefundStatus;
use App\Filament\Resources\RefundRequestResource\Pages;
use App\Models\RefundRequest;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RefundRequestResource extends Resource
{
    protected static ?string $model = RefundRequest::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-arrow-uturn-left';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Commerce';
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'id';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Refund Details')
                    ->schema([
                        Forms\Components\Select::make('order_id')
                            ->relationship('order', 'order_number')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('amount_requested')
                            ->label('Amount Requested')
                            ->numeric()
                            ->prefix('€')
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options(RefundStatus::class)
                            ->required()
                            ->default(RefundStatus::Pending),
                        Forms\Components\Textarea::make('admin_note')
                            ->label('Admin Note')
                            ->nullable()
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\DateTimePicker::make('processed_at')
                            ->nullable(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Order #')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Order number copied')
                    ->extraAttributes(['class' => 'oem-number'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->getStateUsing(fn (RefundRequest $record): string => $record->user?->name ?? $record->order?->shipping_name ?? $record->order?->guest_email ?? '—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                                ->orWhereHas('order', fn ($o) => $o->where('shipping_name', 'like', "%{$search}%")
                                    ->orWhere('guest_email', 'like', "%{$search}%"));
                        });
                    })
                    ->limit(25),
                Tables\Columns\TextColumn::make('amount_requested')
                    ->label('Amount')
                    ->getStateUsing(fn (RefundRequest $record): string => format_money($record->amount_requested))
                    ->sortable(),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(40)
                    ->tooltip(fn (RefundRequest $record): string => $record->reason),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (RefundStatus $state): string => match ($state) {
                        RefundStatus::Pending   => 'warning',
                        RefundStatus::Approved  => 'info',
                        RefundStatus::Rejected  => 'danger',
                        RefundStatus::Processed => 'success',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M j, Y H:i')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(RefundStatus::class)
                    ->multiple(),
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
                Tables\Filters\Filter::make('amount_range')
                    ->form([
                        Forms\Components\TextInput::make('min_amount')
                            ->label('Min Amount')
                            ->numeric(),
                        Forms\Components\TextInput::make('max_amount')
                            ->label('Max Amount')
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['min_amount'], fn ($q, $val) => $q->where('amount_requested', '>=', $val))
                            ->when($data['max_amount'], fn ($q, $val) => $q->where('amount_requested', '<=', $val));
                    }),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (RefundRequest $record): bool => $record->status === RefundStatus::Pending)
                    ->action(function (RefundRequest $record): void {
                        $record->status = RefundStatus::Approved;
                        $record->save();

                        Notification::make()
                            ->title('Refund approved')
                            ->success()
                            ->send();
                    }),
                Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->schema([
                        Forms\Components\Textarea::make('admin_note')
                            ->label('Rejection Note')
                            ->required()
                            ->rows(3)
                            ->placeholder('Reason for rejection...'),
                    ])
                    ->visible(fn (RefundRequest $record): bool => $record->status === RefundStatus::Pending)
                    ->action(function (RefundRequest $record, array $data): void {
                        $record->status = RefundStatus::Rejected;
                        $record->admin_note = $data['admin_note'];
                        $record->save();

                        Notification::make()
                            ->title('Refund rejected')
                            ->danger()
                            ->send();
                    }),
                Actions\Action::make('markProcessed')
                    ->label('Mark Processed')
                    ->icon('heroicon-o-banknotes')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (RefundRequest $record): bool => $record->status === RefundStatus::Approved)
                    ->action(function (RefundRequest $record): void {
                        $record->status = RefundStatus::Processed;
                        $record->processed_at = now();
                        $record->save();

                        Notification::make()
                            ->title('Refund marked as processed')
                            ->success()
                            ->send();
                    }),
                Actions\EditAction::make(),
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
        return [
            \App\Filament\Resources\RefundRequestResource\RelationManagers\OrderSummaryRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRefundRequests::route('/'),
            'view'   => Pages\ViewRefundRequest::route('/{record}'),
            'edit'   => Pages\EditRefundRequest::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', RefundStatus::Pending)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $count = static::getModel()::where('status', RefundStatus::Pending)->count();

        return $count > 0 ? 'warning' : null;
    }
}
