<?php

namespace App\Filament\Resources\CronLogResource\Pages;

use App\Filament\Resources\CronLogResource;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Support\HasDrilldownFilters;

class ListCronLogs extends ListRecords
{
    use HasDrilldownFilters;

    protected static string $resource = CronLogResource::class;
}
