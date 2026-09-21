<?php

namespace App\Filament\Resources\LoginLogResource\Pages;

use App\Filament\Resources\LoginLogResource;
use App\Filament\Support\HasDrilldownFilters;
use Filament\Resources\Pages\ListRecords;

class ListLoginLogs extends ListRecords
{
    use HasDrilldownFilters;

    protected static string $resource = LoginLogResource::class;
}
