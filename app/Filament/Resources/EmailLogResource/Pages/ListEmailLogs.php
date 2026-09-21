<?php

namespace App\Filament\Resources\EmailLogResource\Pages;

use App\Filament\Resources\EmailLogResource;
use App\Filament\Support\HasDrilldownFilters;
use Filament\Resources\Pages\ListRecords;

class ListEmailLogs extends ListRecords
{
    use HasDrilldownFilters;

    protected static string $resource = EmailLogResource::class;
}
