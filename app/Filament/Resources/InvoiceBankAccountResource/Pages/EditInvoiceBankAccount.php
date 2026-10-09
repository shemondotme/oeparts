<?php

namespace App\Filament\Resources\InvoiceBankAccountResource\Pages;

use App\Filament\Resources\InvoiceBankAccountResource;
use Filament\Resources\Pages\EditRecord;

class EditInvoiceBankAccount extends EditRecord
{
    protected static string $resource = InvoiceBankAccountResource::class;

    protected function getRedirectUrl(): ?string
    {
        return InvoiceBankAccountResource::getUrl('index');
    }
}
