<?php

namespace App\Filament\Resources\FailedSearchLogResource\Pages;

use App\Filament\Resources\FailedSearchLogResource;
use App\Filament\Support\HasDrilldownFilters;
use App\Filament\Support\HasSavedViews;
use Filament\Resources\Pages\ListRecords;

class ListFailedSearchLogs extends ListRecords
{
    use HasDrilldownFilters, HasSavedViews;
    protected static string $resource = FailedSearchLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...$this->getSavedViewHeaderActions(),
        ];
    }
}
