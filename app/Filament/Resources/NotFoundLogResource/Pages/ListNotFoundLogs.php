<?php

namespace App\Filament\Resources\NotFoundLogResource\Pages;

use App\Filament\Resources\NotFoundLogResource;
use App\Filament\Support\HasDrilldownFilters;
use App\Filament\Support\HasSavedViews;
use Filament\Resources\Pages\ListRecords;

class ListNotFoundLogs extends ListRecords
{
    use HasDrilldownFilters, HasSavedViews;

    protected static string $resource = NotFoundLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...$this->getSavedViewHeaderActions(),
        ];
    }
}
