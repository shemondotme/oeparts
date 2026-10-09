<?php

namespace App\Filament\Resources\InvoiceBankAccountResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\InvoiceBankAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoiceBankAccount extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = InvoiceBankAccountResource::class;

    protected function getRedirectUrl(): string
    {
        return InvoiceBankAccountResource::getUrl('index');
    }
}
