<?php

namespace App\Filament\Resources\SearchLogResource\Pages;

use App\Filament\Resources\SearchLogResource;
use App\Filament\Support\HasDrilldownFilters;
use Filament\Resources\Pages\ListRecords;

class ListSearchLogs extends ListRecords
{
    use HasDrilldownFilters;

    protected static string $resource = SearchLogResource::class;
}
