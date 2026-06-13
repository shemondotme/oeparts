<?php

namespace App\Filament\Resources;

use App\Enums\RefundStatus;
use App\Filament\Resources\RefundRequestResource\Pages;
use App\Filament\Support\AdminUi;
use App\Jobs\SendRefundProcessedEmail;
use App\Models\RefundRequest;
use Filament\Actions;
use Filament\Forms;
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
                Grid::make(['default' => 1, 'xl' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 2])
                            ->schema([
                                Section::make('Request Details')
                                    ->icon('heroicon-o-document-text')
                                    ->description('Core refund context and customer-provided reason.')
                                    ->schema([
                                        Forms\Components\Select::make('order_id')
                                            ->label('Original Order')
                                            ->relationship('order', 'order_number')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->helperText('The order this refund request is associated with.'),
                                        Forms\Components\Select::make('user_id')
                                            ->label('Customer')
                                            ->relationship('user', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->helperText('The customer who submitted this refund request. May be empty for guest orders.'),
                                        Forms\Components\Textarea::make('reason')
                                            ->label('Refund Reason')
                                            ->placeholder('e.g. Wrong part received, part damaged in transit...')
                                            ->helperText('Customer-provided explanation for why they are requesting a refund.')
                                            ->required()
                                            ->rows(4)
                                            ->columnSpanFull(),
                                        Forms\Components\Textarea::make('admin_note')
                                            ->label('Internal Admin Note')
                                            ->placeholder('e.g. Verified with carrier, item was damaged...')
                                            ->helperText('Private note for internal reference. Not visible to the customer.')
                                            ->nullable()
                                            ->rows(3)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Status & Processing')
                                    ->icon('heroicon-o-arrow-path')
                                    ->description('Track the current state and processing timeline of this refund.')
                                    ->schema([
                                        Forms\Components\Select::make('status')
                                            ->label('Refund Status')
                                            ->options(RefundStatus::class)
                                            ->required()
                                            ->default(RefundStatus::Pending)
                                            ->helperText('Current processing state of this refund request.'),
                                        Forms\Components\DateTimePicker::make('processed_at')
                                            ->label('Processed At')
                                            ->nullable()
                                            ->helperText('Date and time the refund was finalized. Set automatically when approved.'),
                                    ]),
                                Section::make('Financials')
                                    ->icon('heroicon-o-banknotes')
                                    ->description('Refund amount details for this request.')
                                    ->extraAttributes(['class' => 'op-financials-form'])
                                    ->schema([
                                        Forms\Components\TextInput::make('amount_requested')
                                            ->label('Refund Amount Requested')
                                            ->numeric()
                                            ->prefix('€')
                                            ->required()
                                            ->minValue(0.01)
                                            ->step(0.01)
                                            ->placeholder('0.00')
                                            ->extraAttributes(['class' => 'op-fin-form-total']),
                                    ]),
                                Section::make('Return Images')
                                    ->icon('heroicon-o-photo')
                                    ->description('Customer-submitted images showing the part condition or damage.')
                                    ->collapsed()
                                    ->schema([
                                        Forms\Components\Placeholder::make('return_images_display')
                                            ->label('Submitted Images')
                                            ->content(function ($record) {
                                                $images = $record->return_images ?? [];
                                                if (empty($images)) {
                                                    return 'No images submitted with this refund request.';
                                                }
                                                return implode("\n", $images);
                                            })
                                            ->extraAttributes(['class' => 'whitespace-pre-wrap']),
                                    ])
                                    ->visible(fn ($record) => filled($record?->return_images) && count($record->return_images) > 0),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->modifyQueryUsing(fn ($query) => $query->with(['order', 'user']))
            ->columns([
            AdminUi::copyableColumn('order.order_number', 'Order #', 'Order number copied')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('customer_name')
                ->label('Customer')
                ->getStateUsing(fn (RefundRequest $record): string => $record->user?->name ?? $record->order?->shipping_name ?? $record->order?->guest_email ?? '—')
                ->description(fn (RefundRequest $record): ?string =>
                    $record->user?->email ?? $record->order?->guest_email ?? null
                )
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->where(function ($q) use ($search) {
                        $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('order', fn ($o) => $o->where('shipping_name', 'like', "%{$search}%")
                                ->orWhere('guest_email', 'like', "%{$search}%"));
                    });
                })
                ->limit(30)
                ->toggleable(),
                Tables\Columns\TextColumn::make('amount_requested')
                    ->label('Amount')
                    ->getStateUsing(fn (RefundRequest $record): string => format_money($record->amount_requested))
                    ->alignEnd()
                    ->fontMono()
                    ->weight(FontWeight::Bold)
                    ->sortable(),
                Tables\Columns\TextColumn::make('order.grand_total')
                    ->label('Order Total')
                    ->getStateUsing(fn (RefundRequest $record): string => $record->order ? format_money($record->order->grand_total) : '—')
                    ->alignEnd()
                    ->fontMono()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(40)
                    ->tooltip(fn (RefundRequest $record): string => $record->reason),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->icon(fn (RefundStatus $state): string => match ($state) {
                        RefundStatus::Pending => 'heroicon-o-clock',
                        RefundStatus::Approved => 'heroicon-o-check-circle',
                        RefundStatus::Rejected => 'heroicon-o-x-circle',
                        RefundStatus::Processed => 'heroicon-o-banknotes',
                    })
                    ->color(fn (RefundStatus $state): string => AdminUi::refundStatusColor($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Refund Status')
                    ->options(RefundStatus::class)
                    ->multiple()
                    ->native(false)
                    ->columnSpan(1),
                Tables\Filters\Filter::make('amount_range')
                    ->label('Amount Range')
                    ->form([
                        Forms\Components\TextInput::make('min_amount')
                            ->label('Min (€)')
                            ->numeric()
                            ->placeholder('0.00'),
                        Forms\Components\TextInput::make('max_amount')
                            ->label('Max (€)')
                            ->numeric()
                            ->placeholder('999.99'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['min_amount'], fn ($q, $val) => $q->where('amount_requested', '>=', $val))
                            ->when($data['max_amount'], fn ($q, $val) => $q->where('amount_requested', '<=', $val));
                    })
                    ->columnSpan(1),
                Tables\Filters\Filter::make('created_at')
                    ->label('Date Range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    })
                    ->columnSpan(1),
            ])
            ->actions(AdminUi::recordActions([
                static::approveAction(),
                static::rejectAction(),
                static::markProcessedAction(),
                Actions\Action::make('approveAndRefund')
                    ->label('Approve & Refund')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve & Process Refund')
                    ->modalDescription('This will approve the refund request and mark it as processed in one step.')
                    ->schema([
                        Forms\Components\Textarea::make('admin_note')
                            ->label('Refund Note')
                            ->rows(3)
                            ->placeholder('Reason for refund...'),
                    ])
                    ->visible(fn (RefundRequest $record): bool => $record->status === RefundStatus::Pending)
                    ->action(function (RefundRequest $record, array $data): void {
                        $record->status = RefundStatus::Processed;
                        $record->processed_at = now();
                        $record->admin_note = $data['admin_note'] ?? $record->admin_note;
                        $record->save();

                        dispatch(new SendRefundProcessedEmail($record));

                        Notification::make()
                            ->title('Refund approved and processed')
                            ->success()
                            ->send();
                    }),
            ]))
        ->bulkActions([
            Actions\BulkActionGroup::make([
                AdminUi::exportCsvBulkAction('Export Refunds', [
                    'order.order_number' => 'Order #',
                    'customer_name' => 'Customer',
                    'amount_requested' => 'Amount',
                    'reason' => 'Reason',
                    'status' => 'Status',
                    'created_at' => 'Date',
                ]),
                Actions\DeleteBulkAction::make(),
            ]),
        ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-arrow-uturn-left')
            ->emptyStateHeading('No refund requests')
            ->emptyStateDescription('Customer refund requests will appear here when submitted. You can review, approve, or reject each request.');
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

    public static function approveAction(): Actions\Action
    {
        return Actions\Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Approve Refund Request')
            ->modalDescription('Mark this refund request as approved. The customer will be notified and you can process the refund manually or mark it as processed.')
            ->visible(fn (RefundRequest $record): bool => $record->status === RefundStatus::Pending)
            ->action(function (RefundRequest $record): void {
                $record->status = RefundStatus::Approved;
                $record->save();

                Notification::make()
                    ->title('Refund approved successfully')
                    ->success()
                    ->send();
            });
    }

    public static function rejectAction(): Actions\Action
    {
        return Actions\Action::make('reject')
            ->label('Reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->modalHeading('Reject Refund Request')
            ->modalDescription('Reject this refund request. The customer will be notified with your reason for rejection.')
            ->schema([
                Forms\Components\Textarea::make('admin_note')
                    ->label('Rejection Reason')
                    ->placeholder('e.g. Part was installed, return window expired, damage caused by customer...')
                    ->required()
                    ->rows(3)
                    ->helperText('Explain why this refund request is being rejected. This will be sent to the customer.'),
            ])
            ->visible(fn (RefundRequest $record): bool => $record->status === RefundStatus::Pending)
            ->action(function (RefundRequest $record, array $data): void {
                $record->status = RefundStatus::Rejected;
                $record->admin_note = $data['admin_note'];
                $record->save();

                Notification::make()
                    ->title('Refund request rejected')
                    ->danger()
                    ->send();
            });
    }

    public static function markProcessedAction(): Actions\Action
    {
        return Actions\Action::make('markProcessed')
            ->label('Mark Processed')
            ->icon('heroicon-o-banknotes')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Mark Refund as Processed')
            ->modalDescription('Confirm that the refund has been successfully processed and the customer has been reimbursed. An email notification will be sent to the customer.')
            ->visible(fn (RefundRequest $record): bool => $record->status === RefundStatus::Approved)
            ->action(function (RefundRequest $record): void {
                $record->status = RefundStatus::Processed;
                $record->processed_at = now();
                $record->save();

                dispatch(new SendRefundProcessedEmail($record));

                Notification::make()
                    ->title('Refund marked as processed')
                    ->success()
                    ->send();
            });
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['order.order_number', 'user.name', 'user.email'];
    }
}
