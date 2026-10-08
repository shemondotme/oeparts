<?php

namespace App\Filament\Resources\CustomInvoiceResource\Pages;

use App\Filament\Resources\CustomInvoiceResource;
use App\Models\CustomInvoice;
use Filament\Resources\Pages\EditRecord;

class EditCustomInvoice extends EditRecord
{
    protected static string $resource = CustomInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CustomInvoiceResource::makeDownloadAction(),
            CustomInvoiceResource::makeSendAction(),
            CustomInvoiceResource::makeMarkPaidAction(),
            CustomInvoiceResource::makeCancelAction(),
        ];
    }

    public function getHeading(): string
    {
        /** @var CustomInvoice $record */
        $record = $this->getRecord();

        return 'Edit '.$record->invoice_number;
    }

    public function getSubheading(): ?string
    {
        return 'Only drafts can be edited. Totals are recalculated on save.';
    }

    protected function getRedirectUrl(): ?string
    {
        return CustomInvoiceResource::getUrl('index');
    }
}
