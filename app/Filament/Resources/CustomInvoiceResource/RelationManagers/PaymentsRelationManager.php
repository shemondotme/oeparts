<?php

namespace App\Filament\Resources\CustomInvoiceResource\RelationManagers;

use App\Models\CustomInvoice;
use App\Models\CustomInvoicePayment;
use App\Services\CustomInvoiceService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Livewire\Attributes\On;

/**
 * The payments received against a document. Payments are added through the "Record
 * payment" action (which keeps the status in step); a mistaken one can be removed here.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments received';

    /** The page's "Record payment" action announces a new payment; re-render this table. */
    #[On('payments-changed')]
    public function refreshPayments(): void
    {
        // Nothing to do: handling any Livewire event re-renders the component and its query.
    }

    private function currencyOfOwner(): string
    {
        /** @var CustomInvoice $owner */
        $owner = $this->getOwnerRecord();

        return (string) $owner->currency;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('paid_on')->label('Date')->date('M j, Y')->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->formatStateUsing(fn ($state, CustomInvoicePayment $record): string => format_price($state, (string) $this->currencyOfOwner()))
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('method')->badge()->placeholder('—'),
                Tables\Columns\TextColumn::make('reference')->placeholder('—'),
                Tables\Columns\TextColumn::make('note')->placeholder('—')->limit(40),
                Tables\Columns\TextColumn::make('creator.name')->label('Recorded by')->placeholder('—'),
            ])
            ->actions([
                Actions\Action::make('removePayment')
                    ->label('Remove')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Removes this payment and moves the invoice status back to match the money that remains.')
                    ->authorize(fn (): bool => auth('admin')->user()?->can('edit custom invoices') ?? false)
                    ->action(function (CustomInvoicePayment $record): void {
                        app(CustomInvoiceService::class)->removePayment($record);
                        Notification::make()->title('Payment removed')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No payments recorded')
            ->emptyStateDescription('Use "Record payment" when money arrives.')
            ->paginated(false);
    }

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        /** @var CustomInvoice $ownerRecord */
        return $ownerRecord->document_type->requestsPayment();
    }
}
