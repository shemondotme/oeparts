<?php

namespace App\Filament\Resources\RefundRequestResource\Pages;

use App\Enums\RefundStatus;
use App\Filament\Resources\RefundRequestResource;
use App\Filament\Support\AdminUi;
use Filament\Actions;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewRefundRequest extends ViewRecord
{
    protected static string $resource = RefundRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RefundRequestResource::approveAction(),
            RefundRequestResource::rejectAction(),
            Actions\ActionGroup::make([
                RefundRequestResource::markProcessedAction(),
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->modalHeading('Delete Refund Request')
                    ->modalDescription('Are you sure you want to delete this refund request? This action cannot be undone.')
                    ->modalSubmitActionLabel('Yes, delete'),
            ])
                ->label('More')
                ->icon('heroicon-o-chevron-down')
                ->color('gray')
                ->button(),
        ];
    }

    public function infolist(Schema $schema): Schema
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
                                    ->schema([
                                        TextEntry::make('order.order_number')
                                            ->label('Order #')
                                            ->copyable()
                                            ->copyMessage('Order number copied')
                                            ->fontMono()
                                            ->weight('bold'),
                                        TextEntry::make('user.name')
                                            ->label('Customer')
                                            ->getStateUsing(fn ($record): string => $record->user?->name ?? $record->order?->shipping_name ?? '—'),
                                        TextEntry::make('user.email')
                                            ->label('Email')
                                            ->getStateUsing(fn ($record): string => $record->user?->email ?? $record->order?->guest_email ?? '—')
                                            ->size('sm')
                                            ->color('gray'),
                                        TextEntry::make('reason')
                                            ->label('Reason')
                                            ->columnSpanFull()
                                            ->extraAttributes(['class' => 'op-refund-reason']),
                                        TextEntry::make('admin_note')
                                            ->label('Admin Note')
                                            ->default('—')
                                            ->columnSpanFull()
                                            ->size('sm')
                                            ->color('gray'),
                                    ])
                                    ->columns(2),
                                Section::make('Related Order Snapshot')
                                    ->icon('heroicon-o-shopping-bag')
                                    ->schema([
                                        TextEntry::make('order.grand_total')
                                            ->label('Order Total')
                                            ->getStateUsing(fn ($record): string => $record->order ? format_money($record->order->grand_total) : '—')
                                            ->fontMono()
                                            ->weight('bold'),
                                        TextEntry::make('order.status')
                                            ->label('Order Status')
                                            ->getStateUsing(fn ($record): string => $record->order?->status?->label() ?? '—')
                                            ->badge()
                                            ->icon(fn ($record): ?string => $record->order ? match ($record->order->status->value) {
                                                'pending' => 'heroicon-o-clock',
                                                'paid' => 'heroicon-o-check-circle',
                                                'processing' => 'heroicon-o-arrow-path',
                                                'shipped' => 'heroicon-o-truck',
                                                'delivered' => 'heroicon-o-check-badge',
                                                'cancelled' => 'heroicon-o-x-circle',
                                                default => null,
                                            } : null)
                                            ->color(fn ($record): string => $record->order ? AdminUi::orderStatusColor($record->order->status) : 'gray'),
                                        TextEntry::make('order.payment_status')
                                            ->label('Payment')
                                            ->getStateUsing(fn ($record): string => $record->order?->payment_status?->label() ?? '—')
                                            ->badge()
                                            ->icon(fn ($record): ?string => $record->order ? match ($record->order->payment_status->value) {
                                                'pending' => 'heroicon-o-clock',
                                                'paid' => 'heroicon-o-check-circle',
                                                'failed' => 'heroicon-o-x-circle',
                                                'refunded' => 'heroicon-o-receipt-refund',
                                                default => null,
                                            } : null)
                                            ->color(fn ($record): string => $record->order ? AdminUi::paymentStatusColor($record->order->payment_status) : 'gray'),
                                    ])
                                    ->columns(3),
                                Section::make('Return Images')
                                    ->icon('heroicon-o-photo')
                                    ->schema([
                                        ImageEntry::make('return_images')
                                            ->label('')
                                            ->hidden(fn ($record): bool => empty($record->return_images))
                                            ->circular()
                                            ->square()
                                            ->width(120)
                                            ->height(120)
                                            ->columnSpanFull(),
                                        TextEntry::make('return_images')
                                            ->label('')
                                            ->getStateUsing(fn ($record): string => empty($record->return_images) ? 'No return images uploaded' : '')
                                            ->visible(fn ($record): bool => empty($record->return_images)),
                                    ]),
                            ]),
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Status')
                                    ->icon('heroicon-o-arrow-path')
                                    ->schema([
                                        TextEntry::make('status')
                                            ->label('Refund Status')
                                            ->badge()
                                            ->icon(fn (RefundStatus $state): string => match ($state) {
                                                RefundStatus::Pending => 'heroicon-o-clock',
                                                RefundStatus::Approved => 'heroicon-o-check-circle',
                                                RefundStatus::Rejected => 'heroicon-o-x-circle',
                                                RefundStatus::Processed => 'heroicon-o-banknotes',
                                            })
                                            ->color(fn (RefundStatus $state): string => AdminUi::refundStatusColor($state))
                                            ->size('lg'),
                                        TextEntry::make('processed_at')
                                            ->label('Processed At')
                                            ->dateTime('d M Y H:i')
                                            ->placeholder('—')
                                            ->fontMono()
                                            ->size('sm'),
                                    ]),
                                Section::make('Financials')
                                    ->icon('heroicon-o-banknotes')
                                    ->extraAttributes(['class' => 'op-refund-financials'])
                                    ->schema([
                                        TextEntry::make('amount_requested')
                                            ->label('Amount Requested')
                                            ->getStateUsing(fn ($record): string => format_money($record->amount_requested))
                                            ->extraAttributes(['class' => 'op-refund-amount']),
                                    ]),
                                Section::make('Record')
                                    ->icon('heroicon-o-clock')
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->label('Created')
                                            ->dateTime('d M Y H:i')
                                            ->fontMono()
                                            ->size('sm'),
                                        TextEntry::make('updated_at')
                                            ->label('Updated')
                                            ->since()
                                            ->size('sm'),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
