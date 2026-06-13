<?php

namespace App\Filament\Resources\EmailLogResource\Pages;

use App\Filament\Resources\EmailLogResource;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Support\HasDrilldownFilters;

class ListEmailLogs extends ListRecords
{
    use HasDrilldownFilters;

    protected static string $resource = EmailLogResource::class;
}
