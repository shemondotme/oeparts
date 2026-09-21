<?php

namespace App\Filament\Resources\MediaFileResource\Pages;

use App\Filament\Resources\MediaFileResource;
use App\Filament\Support\HasDrilldownFilters;
use Filament\Resources\Pages\ListRecords;

class ListMediaFiles extends ListRecords
{
    use HasDrilldownFilters;

    protected static string $resource = MediaFileResource::class;
}
