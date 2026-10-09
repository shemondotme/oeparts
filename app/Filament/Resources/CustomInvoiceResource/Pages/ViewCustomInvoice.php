<?php

namespace App\Filament\Resources\CustomInvoiceResource\Pages;

use App\Enums\CustomInvoiceStatus;
use App\Filament\Resources\CustomInvoiceResource;
use App\Filament\Resources\CustomInvoiceResource\RelationManagers\PaymentsRelationManager;
use App\Models\CustomInvoice;
use Filament\Actions;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * A read-only view of a document: sent invoices cannot be edited, but their lines,
 * totals, payments and links to related documents still need to be seen somewhere.
 */
class ViewCustomInvoice extends ViewRecord
{
    protected static string $resource = CustomInvoiceResource::class;

    public function getHeading(): string
    {
        /** @var CustomInvoice $record */
        $record = $this->getRecord();

        return $record->document_type->getLabel().' '.$record->invoice_number;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->visible(fn (CustomInvoice $record): bool => $record->isEditable()),
            CustomInvoiceResource::makePreviewAction(),
            CustomInvoiceResource::makeDownloadAction(),
            CustomInvoiceResource::makeSendAction(),
            CustomInvoiceResource::makeRecordPaymentAction(),
            CustomInvoiceResource::makeReminderAction(),
            CustomInvoiceResource::makeAcceptAction(),
            CustomInvoiceResource::makeDeclineAction(),
            CustomInvoiceResource::makeMarkPaidAction(),
            ...CustomInvoiceResource::makeConvertActions(),
            CustomInvoiceResource::makeCreditNoteAction(),
            CustomInvoiceResource::makeDuplicateAction(),
            CustomInvoiceResource::makeCancelAction(),
        ];
    }

    public function getRelationManagers(): array
    {
        return [PaymentsRelationManager::class];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Summary')
                ->icon('heroicon-o-document-text')
                ->schema([
                    TextEntry::make('document_type')->label('Type')->badge(),
                    TextEntry::make('invoice_number')->label('Number')->extraAttributes(['class' => 'font-mono']),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('overdue')
                        ->label('Overdue')
                        ->state(fn (CustomInvoice $record): ?string => $record->isOverdue() ? $record->daysOverdue().' days' : null)
                        ->badge()
                        ->color('danger')
                        ->visible(fn (CustomInvoice $record): bool => $record->isOverdue()),
                    TextEntry::make('parent.invoice_number')
                        ->label('Created from')
                        ->placeholder('—')
                        ->url(fn (CustomInvoice $record): ?string => $record->parent ? CustomInvoiceResource::getUrl('view', ['record' => $record->parent]) : null),
                    TextEntry::make('client_name')->label('Client'),
                    TextEntry::make('client_company')->label('Company')->placeholder('—'),
                    TextEntry::make('issue_date')->date('M j, Y'),
                    TextEntry::make('due_date')->label('Due / valid until')->date('M j, Y'),
                    TextEntry::make('total')
                        ->formatStateUsing(fn ($state, CustomInvoice $record): string => format_price($state, $record->currency)),
                    TextEntry::make('amount_paid')
                        ->label('Paid so far')
                        ->state(fn (CustomInvoice $record): string => format_price($record->amountPaid(), $record->currency))
                        ->visible(fn (CustomInvoice $record): bool => $record->document_type->requestsPayment()),
                    TextEntry::make('balance_due')
                        ->label('Balance due')
                        ->state(fn (CustomInvoice $record): string => format_price($record->balanceDue(), $record->currency))
                        ->weight('bold')
                        ->visible(fn (CustomInvoice $record): bool => $record->document_type->requestsPayment()),
                    TextEntry::make('reminder_count')
                        ->label('Reminders sent')
                        ->state(fn (CustomInvoice $record): string => $record->reminder_count.($record->last_reminded_at ? ' (last '.$record->last_reminded_at->diffForHumans().')' : ''))
                        ->visible(fn (CustomInvoice $record): bool => $record->document_type->requestsPayment()),
                ])
                ->columns(3),

            Section::make('Lines')
                ->icon('heroicon-o-list-bullet')
                ->schema([
                    RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('part_number')->label('Part no.')->placeholder('—')->extraAttributes(['class' => 'font-mono']),
                            TextEntry::make('description')->columnSpan(2),
                            TextEntry::make('quantity'),
                            TextEntry::make('unit_price')->label('Unit price'),
                        ])
                        ->columns(5),
                ]),

            Section::make('Related documents')
                ->icon('heroicon-o-link')
                ->schema([
                    RepeatableEntry::make('children')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('document_type')->label('Type')->badge(),
                            TextEntry::make('invoice_number')->label('Number')
                                ->url(fn ($record): string => CustomInvoiceResource::getUrl('view', ['record' => $record])),
                            TextEntry::make('status')->badge(),
                        ])
                        ->columns(3),
                ])
                ->visible(fn (CustomInvoice $record): bool => $record->children()->exists()),
        ]);
    }

    /** Status shortcut used by the view's visibility rules. */
    public static function isOpen(CustomInvoice $record): bool
    {
        return in_array($record->status, [CustomInvoiceStatus::Sent, CustomInvoiceStatus::PartiallyPaid], true);
    }
}
