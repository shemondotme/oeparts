<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource;
use App\Models\OrderNote;
use App\Models\OrderStatusHistory;
use App\Services\InvoiceService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
                ->action(function (array $data): void {
                    $record = $this->getRecord();
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

                    $this->dispatch('$refresh');
                }),
            Actions\Action::make('addNote')
                ->label('Add Internal Note')
                ->icon('heroicon-o-chat-bubble-left')
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
                ->color('primary')
                ->action(function (): void {
                    $record = $this->getRecord();

                    if (!$record->invoice_number) {
                        $record->invoice_number = app(InvoiceService::class)->nextInvoiceNumber();
                        $record->save();
                    }

                    Notification::make()
                        ->title('Invoice generated')
                        ->body("Invoice: {$record->invoice_number}")
                        ->success()
                        ->send();
                }),
            Actions\Action::make('toggleUrgent')
                ->label(fn () => $this->getRecord()->urgent_processing ? 'Remove Urgent' : 'Mark Urgent')
                ->icon('heroicon-o-exclamation-triangle')
                ->color(fn () => $this->getRecord()->urgent_processing ? 'gray' : 'danger')
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
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Order Items')
                    ->schema([
                        \Filament\Infolists\Components\RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('oem_number_snapshot')
                                    ->label('OEM Number')
                                    ->extraAttributes(['class' => 'oem-number']),
                                \Filament\Infolists\Components\TextEntry::make('manufacturer_snapshot')
                                    ->label('Manufacturer'),
                                \Filament\Infolists\Components\TextEntry::make('condition_snapshot')
                                    ->label('Condition')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'new'            => 'success',
                                        'used_grade_a'   => 'info',
                                        'used_grade_b'   => 'warning',
                                        'used_grade_c'   => 'gray',
                                        'remanufactured' => 'purple',
                                        'aftermarket'    => 'danger',
                                        'new_old_stock'  => 'teal',
                                        default          => 'gray',
                                    }),
                                \Filament\Infolists\Components\TextEntry::make('quantity')
                                    ->label('Qty')
                                    ->alignCenter(),
                                \Filament\Infolists\Components\TextEntry::make('unit_price')
                                    ->label('Unit Price')
                                    ->getStateUsing(fn ($record): string => format_money($record->unit_price)),
                                \Filament\Infolists\Components\TextEntry::make('total_price')
                                    ->label('Total')
                                    ->getStateUsing(fn ($record): string => format_money($record->total_price))
                                    ->weight('bold'),
                            ])
                            ->columns(6),
                    ]),

                \Filament\Schemas\Components\Section::make('Order Timeline')
                    ->schema([
                        \Filament\Infolists\Components\RepeatableEntry::make('statusHistory')
                            ->hiddenLabel()
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('new_status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending'         => 'warning',
                                        'paid'            => 'info',
                                        'processing'      => 'primary',
                                        'shipped'         => 'success',
                                        'delivered'       => 'success',
                                        'cancelled'       => 'danger',
                                        'refund_requested'=> 'warning',
                                        'refunded'        => 'gray',
                                        default           => 'gray',
                                    }),
                                \Filament\Infolists\Components\TextEntry::make('note')
                                    ->label('Note')
                                    ->getStateUsing(fn ($record): string => $record->note ?? '—')
                                    ->limit(50),
                                \Filament\Infolists\Components\TextEntry::make('admin.name')
                                    ->label('By')
                                    ->getStateUsing(fn ($record): string => $record->admin?->name ?? 'System'),
                                \Filament\Infolists\Components\TextEntry::make('created_at')
                                    ->label('Timestamp')
                                    ->dateTime('M j, Y H:i'),
                            ])
                            ->columns(4),
                    ]),

                \Filament\Schemas\Components\Section::make('Internal Notes')
                    ->schema([
                        \Filament\Infolists\Components\RepeatableEntry::make('notes')
                            ->hiddenLabel()
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('note')
                                    ->label('Note')
                                    ->columnSpanFull(),
                                \Filament\Infolists\Components\TextEntry::make('admin.name')
                                    ->label('By')
                                    ->getStateUsing(fn ($record): string => $record->admin?->name ?? 'System'),
                                \Filament\Infolists\Components\TextEntry::make('created_at')
                                    ->label('Date')
                                    ->dateTime('M j, Y H:i'),
                            ])
                            ->columns(3),
                    ]),
            ]);
    }
}
