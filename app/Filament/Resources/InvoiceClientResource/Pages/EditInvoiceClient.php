<?php

namespace App\Filament\Resources\InvoiceClientResource\Pages;

use App\Filament\Resources\InvoiceClientResource;
use Filament\Resources\Pages\EditRecord;

class EditInvoiceClient extends EditRecord
{
    protected static string $resource = InvoiceClientResource::class;

    protected function getRedirectUrl(): ?string
    {
        return InvoiceClientResource::getUrl('index');
    }
}
