<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Filament\Support\AdminUi;
use App\Filament\Resources\OrderResource;
use App\Models\OrderNote;
use App\Jobs\GenerateInvoicePdf;
use App\Services\PaymentService;
use App\Services\SequenceService;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        return [
            Actions\ActionGroup::make([
                OrderResource::makeChangeStatusAction(),
                Actions\Action::make('toggleUrgent')
                    ->label(fn () => $record->urgent_processing ? 'Remove Urgent' : 'Mark Urgent')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color(fn () => $record->urgent_processing ? 'gray' : 'danger')
                    ->authorize('update')
                    ->action(function (): void {
                        $record = $this->getRecord();
                        $record->urgent_processing = !$record->urgent_processing;
                        $record->save();

                        Notification::make()
                            ->title($record->urgent_processing ? 'Order marked as urgent' : 'Urgent flag removed')
                            ->success()
                            ->send();

                        $this->dispatch('$refresh');
                    }),
                Actions\Action::make('addNote')
                    ->label('Add Internal Note')
                    ->icon('heroicon-o-chat-bubble-left')
                    ->authorize('update')
                    ->schema([
                        Forms\Components\Textarea::make('note')
                            ->label('Note')
                            ->required()
                            ->rows(4),
                    ])
                    ->action(function (array $data): void {
                        OrderNote::create([
                            'order_id' => $this->getRecord()->id,
                            'admin_id' => auth('admin')->id(),
                            'note'     => $data['note'],
                        ]);

                        Notification::make()
                            ->title('Note added')
                            ->success()
                            ->send();

                        $this->dispatch('$refresh');
                    }),
                Actions\Action::make('generateInvoice')
                    ->label('Generate Invoice PDF')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->authorize('update')
                    ->action(function (): void {
                        $record = $this->getRecord();

                        if (! $record->invoice_number) {
                            $record->invoice_number = app(SequenceService::class)->nextInvoiceNumber();
                            $record->save();
                        }

                        GenerateInvoicePdf::dispatch($record);

                        Notification::make()
                            ->title('Invoice generated')
                            ->body("Invoice {$record->invoice_number} is being generated.")
                            ->success()
                            ->send();
                    }),
            ])
                ->label('Actions')
                ->icon('heroicon-o-chevron-down')
                ->color('gray')
                ->button(),
            Actions\Action::make('confirmPayment')
                ->label('Confirm Payment')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->authorize('update')
                ->requiresConfirmation()
                ->modalHeading('Confirm Bank Transfer Payment')
                ->modalDescription('Mark this order as paid after verifying the bank transfer has been received.')
                ->schema([
                    Forms\Components\TextInput::make('transaction_id')
                        ->label('Transaction Reference')
                        ->maxLength(200)
                        ->placeholder('Bank transfer reference or transaction ID'),
                ])
                ->action(function (array $data): void {
                    $record = $this->getRecord();

                    $payment = \App\Models\Payment::firstOrCreate(
                        ['order_id' => $record->id],
                        [
                            'gateway'        => PaymentGateway::BankTransfer,
                            'transaction_id' => $data['transaction_id'] ?? null,
                            'status'         => PaymentTransactionStatus::Pending,
                            'amount'         => $record->grand_total,
                        ]
                    );

                    if (! empty($data['transaction_id']) && ! $payment->transaction_id) {
                        $payment->update(['transaction_id' => $data['transaction_id']]);
                    }

                    try {
                        app(PaymentService::class)->confirmBankTransferPayment(
                            $payment,
                            $data['transaction_id'] ?? '',
                            auth('admin')->id(),
                        );

                        Notification::make()
                            ->title('Payment confirmed')
                            ->body("Order {$record->order_number} marked as paid.")
                            ->success()
                            ->send();

                        $this->dispatch('$refresh');
                    } catch (\RuntimeException $e) {
                        Notification::make()
                            ->title('Confirmation failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn (): bool =>
                    $this->getRecord()->payment_method === PaymentMethod::BankTransfer
                    && $this->getRecord()->payment_status === PaymentStatus::Pending
                ),
            Actions\EditAction::make(),
            Actions\ActionGroup::make([
                Actions\DeleteAction::make()
                    ->modalDescription('Are you sure you want to delete this order? Prefer cancelling via "Change Status" — deletion is for test/junk orders only.'),
            ])
                ->icon('heroicon-o-ellipsis-vertical')
                ->color('gray'),
        ];
    }

    /**
     * Mutating relation managers (Items, Notes) live on the edit page; the
     * infolist + timeline already present that data read-only here.
     */
    public function getRelationManagers(): array
    {
        return [
            \App\Filament\Resources\OrderResource\RelationManagers\PaymentRelationManager::class,
            \App\Filament\Resources\OrderResource\RelationManagers\RefundRequestRelationManager::class,
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        $record = $this->getRecord();

        return $schema
            ->components([
                Grid::make(['default' => 1, 'xl' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Group::make([
                            Section::make('Order Items')
                                ->icon('heroicon-o-shopping-cart')
                                ->extraAttributes(['class' => 'op-order-items-section'])
                                ->schema([
                                    RepeatableEntry::make('items')
                                        ->hiddenLabel()
                                        ->extraAttributes(['class' => 'op-order-items-table'])
                                        // 2-up pairs on small screens; full 6-column row from xl.
                                        ->schema([
                                            TextEntry::make('oem_number_snapshot')
                                                ->label('OEM Number')
                                                ->fontMono()
                                                ->weight('bold')
                                                ->extraAttributes(['class' => 'oem-number']),
                                            TextEntry::make('manufacturer_snapshot')
                                                ->label('Manufacturer'),
                                            TextEntry::make('condition_snapshot')
                                                ->label('Condition')
                                                ->badge()
                                                ->color(fn ($state): string => match (true) {
                                                    str_contains(strtolower($state ?? ''), 'new') => 'success',
                                                    str_contains(strtolower($state ?? ''), 'used') => 'warning',
                                                    default => 'gray',
                                                }),
                                            TextEntry::make('quantity')
                                                ->label('Qty')
                                                ->fontMono(),
                                            TextEntry::make('unit_price')
                                                ->label('Unit Price')
                                                ->fontMono()
                                                ->getStateUsing(fn ($record): string => format_money($record->unit_price)),
                                            TextEntry::make('total_price')
                                                ->label('Total')
                                                ->fontMono()
                                                ->weight('bold')
                                                ->getStateUsing(fn ($record): string => format_money($record->total_price)),
                                        ])
                                        ->columns(['default' => 2, 'xl' => 6]),
                                    \Filament\Infolists\Components\TextEntry::make('items_summary')
                                        ->hiddenLabel()
                                        ->default(function () use ($record): string {
                                            $count = $record->items->count();
                                            $total = $record->items->reduce(
                                                fn (string $carry, $item): string => bcadd($carry, (string) $item->total_price, 2),
                                                '0.00'
                                            );
                                            return "{$count} item(s) — Total: " . format_money($total);
                                        })
                                        ->extraAttributes(['class' => 'op-order-items-footer']),
                                ]),
                            Section::make('Shipping Address')
                                ->icon('heroicon-o-map-pin')
                                ->extraAttributes(['class' => 'op-shipping-section'])
                                ->schema([
                                    TextEntry::make('shipping_name')
                                        ->label('Recipient')
                                        ->weight('semibold'),
                                    TextEntry::make('shipping_address_line1')
                                        ->label('Address'),
                                    TextEntry::make('shipping_city')
                                        ->label('City'),
                                    TextEntry::make('shipping_postal_code')
                                        ->label('Postal Code'),
                                    TextEntry::make('shipping_country_code')
                                        ->label('Country')
                                        ->formatStateUsing(fn (?string $state): string => $state
                                            ? (config('countries')[$state] ?? $state) . " ({$state})"
                                            : '—')
                                        ->badge()
                                        ->color('gray'),
                                ])
                                ->columns(2),
                            Section::make('Additional')
                                ->icon('heroicon-o-chat-bubble-left-right')
                                ->extraAttributes(['class' => 'op-additional-section'])
                                ->schema([
                                    TextEntry::make('customer_note')
                                        ->label('Customer Note')
                                        ->default('—')
                                        ->columnSpanFull(),
                                    TextEntry::make('tracking_number')
                                        ->label('Tracking Number')
                                        ->default('—')
                                        ->fontMono()
                                        ->url(fn ($record): ?string => $record->tracking_url)
                                        ->openUrlInNewTab()
                                        ->color(fn ($record): ?string => $record->tracking_url ? 'primary' : null)
                                        ->tooltip(fn ($record): ?string => $record->tracking_url ? 'Open carrier tracking page' : null),
                                    TextEntry::make('carrier_name')
                                        ->label('Carrier')
                                        ->default('—'),
                                    TextEntry::make('invoice_number')
                                        ->label('Invoice Number')
                                        ->default('—')
                                        ->fontMono(),
                                ])
                                ->columns(2),
                            Section::make('Order Timeline')
                                ->icon('heroicon-o-clock')
                                ->description('Chronological history of status changes and notes.')
                                ->extraAttributes(['class' => 'op-timeline-section'])
                                ->schema([
                                    $this->getTimelineEntries(),
                                ])
                                ->collapsible(),
                        ])
                            ->columnSpan(['default' => 1, 'xl' => 2]),
                        Group::make([
                            Section::make('Summary')
                                ->icon('heroicon-o-document-text')
                                ->extraAttributes(['class' => 'op-summary-section'])
                                ->schema([
                                    TextEntry::make('order_number')
                                        ->label('Order #')
                                        ->copyable()
                                        ->copyMessage('Order number copied')
                                        ->size('lg')
                                        ->weight('bold')
                                        ->fontMono(),
                                    TextEntry::make('created_at')
                                        ->label('Placed At')
                                        ->dateTime('d M Y H:i')
                                        ->tooltip(fn ($record): string => $record->created_at->diffForHumans())
                                        ->fontMono()
                                        ->size('sm'),
                                    TextEntry::make('customer_name')
                                        ->label('Customer')
                                        ->default('—')
                                        ->getStateUsing(fn ($record): string => $record->shipping_name ?? $record->user?->name ?? $record->guest_email ?? '—'),
                                    TextEntry::make('customer_email')
                                        ->label('Email')
                                        ->default('—')
                                        ->getStateUsing(fn ($record): ?string => $record->user?->email ?? $record->guest_email)
                                        ->size('sm')
                                        ->color('gray'),
                                    TextEntry::make('urgent_processing')
                                        ->label('Priority')
                                        ->badge()
                                        ->formatStateUsing(fn (bool $state): string => $state ? 'Urgent' : 'Standard')
                                        ->color(fn (bool $state): string => $state ? 'warning' : 'gray')
                                        ->icon(fn (bool $state): string => $state ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check'),
                                ]),
                            Section::make('Status')
                                ->icon('heroicon-o-arrow-path')
                                ->extraAttributes(['class' => 'op-status-section'])
                                ->schema([
                                    TextEntry::make('status')
                                        ->label('Order Status')
                                        ->badge()
                                        ->icon(fn ($state): string => match ($state->value) {
                                            'pending' => 'heroicon-o-clock',
                                            'paid' => 'heroicon-o-check-circle',
                                            'processing' => 'heroicon-o-arrow-path',
                                            'shipped' => 'heroicon-o-truck',
                                            'delivered' => 'heroicon-o-check-badge',
                                            'cancelled' => 'heroicon-o-x-circle',
                                            'refund_requested' => 'heroicon-o-arrow-uturn-left',
                                            'refunded' => 'heroicon-o-receipt-refund',
                                            default => 'heroicon-o-question-mark-circle',
                                        })
                                        ->color(fn ($state): string => AdminUi::orderStatusColor($state))
                                        ->size('lg'),
                                    TextEntry::make('payment_status')
                                        ->label('Payment')
                                        ->badge()
                                        ->icon(fn ($state): string => match ($state->value) {
                                            'pending' => 'heroicon-o-clock',
                                            'paid' => 'heroicon-o-check-circle',
                                            'failed' => 'heroicon-o-x-circle',
                                            'refunded' => 'heroicon-o-receipt-refund',
                                            default => 'heroicon-o-question-mark-circle',
                                        })
                                        ->color(fn ($state): string => AdminUi::paymentStatusColor($state)),
                                    TextEntry::make('payment_method')
                                        ->label('Method'),
                                    TextEntry::make('payment_reference')
                                        ->label('Reference')
                                        ->default('—')
                                        ->fontMono()
                                        ->size('sm'),
                                ]),
                            Section::make('Financials')
                                ->icon('heroicon-o-banknotes')
                                ->extraAttributes(['class' => 'op-financials-section'])
                                ->schema([
                                    TextEntry::make('subtotal')
                                        ->money('EUR')
                                        ->size('sm')
                                        ->extraAttributes(['class' => 'op-fin-line']),
                                    TextEntry::make('discount_amount')
                                        ->label('Discount')
                                        ->formatStateUsing(fn ($state): string => bccomp((string) $state, '0.00', 2) === 1
                                            ? '− ' . format_money($state)
                                            : format_money($state))
                                        ->color(fn ($state): ?string => bccomp((string) $state, '0.00', 2) === 1 ? 'success' : null)
                                        ->size('sm')
                                        ->extraAttributes(['class' => 'op-fin-line']),
                                    TextEntry::make('shipping_cost')
                                        ->label('Shipping')
                                        ->money('EUR')
                                        ->size('sm')
                                        ->extraAttributes(['class' => 'op-fin-line']),
                                    TextEntry::make('urgent_processing_fee')
                                        ->label('Rush Processing')
                                        ->money('EUR')
                                        ->size('sm')
                                        ->color('warning')
                                        ->visible(fn ($record): bool => $record->urgent_processing && bccomp((string) $record->urgent_processing_fee, '0.00', 2) === 1)
                                        ->extraAttributes(['class' => 'op-fin-line']),
                                    TextEntry::make('vat_amount')
                                        ->label('VAT')
                                        ->money('EUR')
                                        ->size('sm')
                                        ->extraAttributes(['class' => 'op-fin-line']),
                                    \Filament\Infolists\Components\TextEntry::make('fin_divider')
                                        ->hiddenLabel()
                                        ->default('')
                                        ->extraAttributes(['class' => 'op-fin-divider']),
                                    TextEntry::make('grand_total')
                                        ->label('Grand Total')
                                        ->money('EUR')
                                        ->weight('bold')
                                        ->size('lg')
                                        ->extraAttributes(['class' => 'op-fin-total']),
                                ]),
                            Section::make('B2B')
                                ->icon('heroicon-o-building-office')
                                ->hidden(fn ($record): bool => ! $record->is_b2b)
                                ->schema([
                                    TextEntry::make('company_name')->label('Company')->default('—'),
                                    TextEntry::make('vat_number')->label('VAT Number')->default('—')->fontMono(),
                                    TextEntry::make('vat_exempt')
                                        ->label('VAT Exempt')
                                        ->badge()
                                        ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                                        ->color(fn (bool $state): string => $state ? 'warning' : 'gray'),
                                ]),
                        ])
                            ->columnSpan(['default' => 1, 'xl' => 1]),
                    ]),
            ]);
    }

    private function getTimelineEntries(): RepeatableEntry
    {
        $order = $this->getRecord();

        $statusHistory = $order->statusHistory()
            ->with('admin')
            ->get()
            ->map(fn ($entry) => [
                'type' => 'status',
                'date' => $entry->created_at,
                'old_status' => $entry->old_status?->value,
                'new_status' => $entry->new_status?->value,
                'admin' => $entry->admin?->name ?? 'System',
                'note' => $entry->note,
            ]);

        $notes = $order->notes()
            ->with('admin')
            ->get()
            ->map(fn ($entry) => [
                'type' => 'note',
                'date' => $entry->created_at,
                'admin' => $entry->admin?->name ?? 'Unknown',
                'note' => $entry->note,
            ]);

        $timeline = $statusHistory->concat($notes)
            ->sortByDesc('date')
            ->values();

        if ($timeline->isEmpty()) {
            return RepeatableEntry::make('timeline')
                ->hiddenLabel()
                ->schema([
                    TextEntry::make('placeholder')
                        ->hiddenLabel()
                        ->color('gray'),
                ])
                ->state([['placeholder' => 'No status changes or internal notes yet.']]);
        }

        return RepeatableEntry::make('timeline')
            ->hiddenLabel()
            ->extraAttributes(['class' => 'op-timeline-entries'])
            ->schema([
                TextEntry::make('date')
                    ->label('When')
                    ->dateTime('d M Y H:i')
                    ->fontMono()
                    ->size('sm')
                    ->weight('bold')
                    ->extraAttributes(['class' => 'op-timeline-date']),
                TextEntry::make('type')
                    ->label('')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'status' => 'Status Change',
                        'note' => 'Internal Note',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'status' => 'info',
                        'note' => 'warning',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'status' => 'heroicon-o-arrow-path',
                        'note' => 'heroicon-o-chat-bubble-left',
                        default => 'heroicon-o-clock',
                    })
                    ->size('sm')
                    ->extraAttributes(['class' => 'op-timeline-type']),
                TextEntry::make('new_status')
                    ->label('')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => ucfirst(str_replace('_', ' ', $state ?? '')))
                    ->color(fn ($state): string => AdminUi::orderStatusColor(OrderStatus::from($state)))
                    ->size('sm')
                    ->hidden(fn ($record): bool => ($record['type'] ?? '') !== 'status')
                    ->extraAttributes(['class' => 'op-timeline-new-status']),
                TextEntry::make('old_status')
                    ->label('')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => ucfirst(str_replace('_', ' ', $state ?? '')))
                    ->color('gray')
                    ->size('sm')
                    ->hidden(fn ($record): bool => ($record['type'] ?? '') !== 'status' || blank($record['old_status'] ?? null))
                    ->extraAttributes(['class' => 'op-timeline-old-status']),
                TextEntry::make('admin')
                    ->label('By')
                    ->size('sm')
                    ->color('gray')
                    ->extraAttributes(['class' => 'op-timeline-admin']),
                TextEntry::make('note')
                    ->label('Note')
                    ->limit(80)
                    ->wrap()
                    ->size('sm')
                    ->extraAttributes(['class' => 'op-timeline-note']),
            ])
            ->state($timeline->toArray())
            ->columns(['default' => 2, 'xl' => 6]);
    }
}
