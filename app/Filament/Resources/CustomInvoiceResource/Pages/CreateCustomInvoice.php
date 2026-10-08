<?php

namespace App\Filament\Resources\CustomInvoiceResource\Pages;

use App\Enums\CustomInvoiceStatus;
use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\CustomInvoiceResource;
use App\Services\CustomInvoiceService;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomInvoice extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = CustomInvoiceResource::class;

    protected ?string $heading = 'New Custom Invoice';

    protected ?string $subheading = 'Write an invoice for a client outside the storefront checkout. It is saved as a draft; email it to the client from the invoice list.';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Number, status and author are never form inputs. The number is drawn
        // from the shared invoice sequence at creation so it is stable from the
        // first save (and cancelled invoices keep theirs).
        $data['invoice_number'] = app(CustomInvoiceService::class)->nextNumber();
        $data['status'] = CustomInvoiceStatus::Draft;
        $data['created_by'] = auth('admin')->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return CustomInvoiceResource::getUrl('index');
    }
}
