<?php

namespace App\Filament\Resources\InvoiceClientResource\Pages;

use App\Filament\Resources\InvoiceClientResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInvoiceClients extends ListRecords
{
    protected static string $resource = InvoiceClientResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
