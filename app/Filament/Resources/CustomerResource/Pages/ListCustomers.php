<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Filament\Support\HasDrilldownFilters;
use App\Filament\Support\HasSavedViews;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    use HasDrilldownFilters, HasSavedViews;
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...$this->getSavedViewHeaderActions(),
        ];
    }
}
