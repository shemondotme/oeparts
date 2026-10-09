<?php

namespace App\Filament\Resources\InvoiceClientResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\InvoiceClientResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoiceClient extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = InvoiceClientResource::class;

    protected function getRedirectUrl(): string
    {
        return InvoiceClientResource::getUrl('index');
    }
}
