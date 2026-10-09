<?php

namespace App\Filament\Resources\InvoiceBankAccountResource\Pages;

use App\Filament\Resources\InvoiceBankAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInvoiceBankAccounts extends ListRecords
{
    protected static string $resource = InvoiceBankAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
