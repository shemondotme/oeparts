<?php

namespace App\Filament\Resources\MediaFileResource\Pages;

use App\Filament\Resources\MediaFileResource;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Support\HasDrilldownFilters;

class ListMediaFiles extends ListRecords
{
    use HasDrilldownFilters;

    protected static string $resource = MediaFileResource::class;
}
