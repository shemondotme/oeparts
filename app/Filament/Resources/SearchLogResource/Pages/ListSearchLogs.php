<?php

namespace App\Filament\Resources\SearchLogResource\Pages;

use App\Filament\Resources\SearchLogResource;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Support\HasDrilldownFilters;

class ListSearchLogs extends ListRecords
{
    use HasDrilldownFilters;

    protected static string $resource = SearchLogResource::class;
}
