<?php

namespace App\Filament\Resources\CronLogResource\Pages;

use App\Filament\Resources\CronLogResource;
use App\Filament\Support\HasDrilldownFilters;
use Filament\Resources\Pages\ListRecords;

class ListCronLogs extends ListRecords
{
    use HasDrilldownFilters;

    protected static string $resource = CronLogResource::class;
}
