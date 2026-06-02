<?php

namespace App\Filament\Resources\RefundRequestResource\Pages;

use App\Enums\RefundStatus;
use App\Filament\Resources\RefundRequestResource;
use App\Models\Order;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewRefundRequest extends ViewRecord
{
    protected static string $resource = RefundRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->getRecord()->status === RefundStatus::Pending)
                ->action(function (): void {
                    $record = $this->getRecord();
                    $record->status = RefundStatus::Approved;
                    $record->save();

                    Notification::make()
                        ->title('Refund approved')
                        ->success()
                        ->send();

                    $this->dispatch('$refresh');
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
                ->visible(fn (): bool => $this->getRecord()->status === RefundStatus::Pending)
                ->action(function (array $data): void {
                    $record = $this->getRecord();
                    $record->status = RefundStatus::Rejected;
                    $record->admin_note = $data['admin_note'];
                    $record->save();

                    Notification::make()
                        ->title('Refund rejected')
                        ->danger()
                        ->send();

                    $this->dispatch('$refresh');
                }),
            Actions\Action::make('markProcessed')
                ->label('Mark Processed')
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->getRecord()->status === RefundStatus::Approved)
                ->action(function (): void {
                    $record = $this->getRecord();
                    $record->status = RefundStatus::Processed;
                    $record->processed_at = now();
                    $record->save();

                    Notification::make()
                        ->title('Refund marked as processed')
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
                Section::make('Order Summary')
                    ->schema([
                        Infolists\Components\TextEntry::make('order.order_number')
                            ->label('Order #')
                            ->extraAttributes(['class' => 'oem-number']),
                        Infolists\Components\TextEntry::make('order.grand_total')
                            ->label('Order Total')
                            ->getStateUsing(fn ($record): string => $record->order ? format_money($record->order->grand_total) : '—'),
                        Infolists\Components\TextEntry::make('order.status')
                            ->label('Order Status')
                            ->getStateUsing(fn ($record): string => $record->order ? $record->order->status->label() : '—')
                            ->badge(),
                        Infolists\Components\TextEntry::make('order.payment_status')
                            ->label('Payment Status')
                            ->getStateUsing(fn ($record): string => $record->order ? $record->order->payment_status->value : '—')
                            ->badge(),
                    ])->columns(4),

                Section::make('Refund Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('amount_requested')
                            ->label('Amount Requested')
                            ->getStateUsing(fn ($record): string => format_money($record->amount_requested))
                            ->size('xl')
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (RefundStatus $state): string => match ($state) {
                                RefundStatus::Pending   => 'warning',
                                RefundStatus::Approved  => 'info',
                                RefundStatus::Rejected  => 'danger',
                                RefundStatus::Processed => 'success',
                            }),
                        Infolists\Components\TextEntry::make('reason')
                            ->label('Reason')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('admin_note')
                            ->label('Admin Note')
                            ->getStateUsing(fn ($record): string => $record->admin_note ?? '—')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('processed_at')
                            ->label('Processed At')
                            ->getStateUsing(fn ($record): string => $record->processed_at ? $record->processed_at->format('M j, Y H:i') : '—'),
                    ])->columns(2),

                Section::make('Customer Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Name')
                            ->getStateUsing(fn ($record): string => $record->user?->name ?? '—'),
                        Infolists\Components\TextEntry::make('user.email')
                            ->label('Email')
                            ->getStateUsing(fn ($record): string => $record->user?->email ?? $record->order?->guest_email ?? '—'),
                    ])->columns(2),

                Section::make('Return Images')
                    ->schema([
                        Infolists\Components\ImageEntry::make('return_images')
                            ->label('')
                            ->hidden(fn ($record): bool => empty($record->return_images))
                            ->circular()
                            ->square()
                            ->width(120)
                            ->height(120)
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('return_images')
                            ->label('')
                            ->getStateUsing(fn ($record): string => empty($record->return_images) ? 'No return images uploaded' : '')
                            ->visible(fn ($record): bool => empty($record->return_images)),
                    ]),
            ]);
    }
}
