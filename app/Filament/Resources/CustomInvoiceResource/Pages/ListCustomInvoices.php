<?php

namespace App\Filament\Resources\CustomInvoiceResource\Pages;

use App\Filament\Resources\CustomInvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomInvoices extends ListRecords
{
    protected static string $resource = CustomInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New custom invoice'),
        ];
    }
}
