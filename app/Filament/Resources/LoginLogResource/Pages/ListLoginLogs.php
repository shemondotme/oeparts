<?php

namespace App\Filament\Resources\LoginLogResource\Pages;

use App\Filament\Resources\LoginLogResource;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Support\HasDrilldownFilters;

class ListLoginLogs extends ListRecords
{
    use HasDrilldownFilters;

    protected static string $resource = LoginLogResource::class;
}
